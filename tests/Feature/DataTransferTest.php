<?php

namespace Tests\Feature;

use App\Domain\DataTransfer\ExportService;
use App\Jobs\ProcessDataExport;
use App\Models\DataTransferJob;
use App\Models\InventoryCostBalance;
use App\Models\InventoryLocation;
use App\Models\OperationalCustomer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class DataTransferTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_preview_is_non_mutating_and_commit_is_retry_safe(): void
    {
        Storage::fake('local');
        $fixture = $this->pricingFixture();
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/imports";
        $before = Product::query()->count();
        $mixed = "sku,name,uom_code,product_type,batch_tracking\nNEW-1,Unicode چائے,EA,standard,no\nNEW-1,Duplicate,EA,standard,no\n";
        $preview = $this->actingAs($fixture['manager'])->post($url, ['resource' => 'catalog', 'file' => UploadedFile::fake()->createWithContent('catalog.csv', $mixed)])
            ->assertCreated()->assertJsonPath('data.state', 'validation_failed')->assertJsonPath('data.invalid_rows', 2);
        $this->assertSame($before, Product::query()->count());
        $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($url.'/'.$preview->json('data.public_id').'/commit')->assertConflict();

        $uom = $fixture['first']->baseUnit->code;
        $valid = "sku,name,uom_code,product_type,batch_tracking\nNEW-2,Green Tea,{$uom},standard,no\n";
        $job = $this->actingAs($fixture['manager'])->post($url, ['resource' => 'catalog', 'file' => UploadedFile::fake()->createWithContent('catalog.csv', $valid)])
            ->assertCreated()->assertJsonPath('data.state', 'validated')->json('data');
        $key = (string) Str::uuid();
        $commit = $url.'/'.$job['public_id'].'/commit';
        $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', $key)->postJson($commit)->assertOk()->assertJsonPath('data.state', 'committed');
        $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', $key)->postJson($commit)->assertOk()->assertJsonPath('data.state', 'committed');
        $this->assertSame(1, ProductVariant::query()->where('sku', 'NEW-2')->count());

        $badHeaders = "local_reference,name,phone,email,customer_balance\nC-1,Ali,03001234567,a@example.test,500\n";
        $this->actingAs($fixture['manager'])->post($url, ['resource' => 'customers', 'file' => UploadedFile::fake()->createWithContent('customers.csv', $badHeaders)])->assertUnprocessable();
    }

    public function test_opening_stock_posts_quantity_and_cost_only_after_commit(): void
    {
        Storage::fake('local');
        $fixture = $this->pricingFixture();
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable', 'is_default' => true]);
        $csv = "sku,location_code,quantity,unit_cost,batch_code,expiry_date\nTEA-1,MAIN,12.500000,80.250000,,\n";
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/imports";
        $job = $this->actingAs($fixture['manager'])->post($url, ['resource' => 'opening_stock', 'file' => UploadedFile::fake()->createWithContent('opening.csv', $csv)])->assertCreated()->assertJsonPath('data.state', 'validated')->json('data');
        $this->assertDatabaseMissing('stock_balances', ['inventory_location_id' => $location->id, 'product_variant_id' => $fixture['first']->id]);
        $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url.'/'.$job['public_id'].'/commit')->assertOk();
        $this->assertSame('12.500000', StockBalance::query()->where('inventory_location_id', $location->id)->firstOrFail()->quantity_base);
        $cost = InventoryCostBalance::query()->where('store_id', $fixture['store']->id)->where('product_variant_id', $fixture['first']->id)->firstOrFail();
        $this->assertSame('12.500000', $cost->quantity_base);
        $this->assertSame('80.250000', $cost->average_unit_cost);
    }

    public function test_queued_export_rechecks_scope_and_escapes_spreadsheet_formulas(): void
    {
        Storage::fake('local');
        Queue::fake();
        $fixture = $this->pricingFixture();
        OperationalCustomer::query()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => null, 'local_reference' => 'CUS-1', 'name' => '=HYPERLINK("bad")', 'email' => 'safe@example.test', 'normalized_email' => 'safe@example.test', 'email_receipt_preference' => false, 'marketing_email_consent' => false, 'version' => 1]);
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/exports";
        $payload = ['resource' => 'customers', 'store_id' => $fixture['store']->public_id];
        $jobData = $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertAccepted()->assertJsonPath('data.state', 'queued')->json('data');
        Queue::assertPushed(ProcessDataExport::class);
        $job = (new DataTransferJob)->resolveRouteBinding($jobData['public_id']);
        $this->assertInstanceOf(DataTransferJob::class, $job);
        app(ExportService::class)->execute($job);
        $download = "/api/v1/merchants/{$fixture['merchant']->public_id}/data-transfers/{$job->public_id}/download";
        $content = $this->actingAs($fixture['manager'])->get($download)->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString("\nCUS-1,=HYPERLINK", $content);
        $this->actingAs($fixture['cashier'])->getJson("/api/v1/merchants/{$fixture['merchant']->public_id}/data-transfers/{$job->public_id}")->assertNotFound();
        $this->assertTrue($job->refresh()->artifactIsCurrent());
    }
}
