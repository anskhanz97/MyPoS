<?php

namespace Tests\Feature;

use App\Domain\Catalog\CatalogSnapshotBuilder;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogProductTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shared_catalog_honours_store_availability_and_fixed_permissions(): void
    {
        $fixture = $this->fixture();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $fixture['merchant']->id, 'code' => 'EA']);
        $payload = $this->productPayload($unit, 'TEA-001', '0001234567890');
        $payload['available_store_ids'] = [$fixture['store']->public_id];
        $payload['extra_groups'] = [['name' => 'Pack-in', 'minimum_selected' => 0, 'maximum_selected' => 1, 'options' => [['name' => 'Gift wrap', 'price_delta' => '50.00']]]];
        $created = $this->actingAs($fixture['manager'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $payload)
            ->assertCreated()->assertJsonPath('data.variants.0.sku', 'TEA-001')->assertJsonPath('data.variants.0.barcodes.0.code', '0001234567890')
            ->assertJsonPath('data.extra_groups.0.options.0.price_delta', '50.00');

        $payload['variants'][0]['barcodes'][0]['code'] = '9999999999999';
        $this->actingAs($fixture['manager'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $payload)->assertUnprocessable();

        $this->actingAs($fixture['cashier'])->getJson($this->productsUrl($fixture['merchant'], $fixture['store']).'?q=000123')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($fixture['cashier'])->getJson($this->productsUrl($fixture['merchant'], $fixture['otherStore']))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($fixture['cashier'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $payload)->assertForbidden();
        $this->assertSame(1, Product::query()->count());
        $this->assertNotEmpty($created->json('data.id'));
    }

    public function test_independent_stores_can_reuse_sku_and_barcode_without_cross_store_reads(): void
    {
        $fixture = $this->fixture(independent: true);
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $fixture['merchant']->id, 'code' => 'EA']);
        $payload = $this->productPayload($unit, 'LOCAL-01', '0000000000123');
        $first = $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $payload)->assertCreated();
        $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['otherStore']), $payload)->assertCreated();

        $this->actingAs($fixture['owner'])->getJson($this->productsUrl($fixture['merchant'], $fixture['otherStore']).'/'.$first->json('data.id'))->assertNotFound();
        $this->assertSame(2, Product::query()->where('merchant_id', $fixture['merchant']->id)->count());
    }

    public function test_archive_preserves_a_sale_ready_snapshot_and_history_detail(): void
    {
        $fixture = $this->fixture();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $fixture['merchant']->id, 'code' => 'EA', 'symbol' => 'ea']);
        $created = $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $this->productPayload($unit, 'ARCH-01', '0000000000999'))->assertCreated();
        $product = Product::query()->sole();
        $variant = ProductVariant::query()->sole();
        $snapshot = app(CatalogSnapshotBuilder::class)->variant($variant, '0000000000999');

        $this->actingAs($fixture['owner'])->patchJson($this->productsUrl($fixture['merchant'], $fixture['store']).'/'.$created->json('data.id'), ['expected_structure_version' => 1, 'archived' => true, 'name' => 'Archived title'])
            ->assertOk()->assertJsonPath('data.structure_version', 2);
        $this->assertSame('Sample product', $snapshot['name']);
        $this->assertSame('0000000000999', $snapshot['barcode_used']);
        $this->assertNotNull($product->refresh()->archived_at);
        $this->actingAs($fixture['cashier'])->getJson($this->productsUrl($fixture['merchant'], $fixture['store']).'/'.$product->public_id)->assertOk()->assertJsonPath('data.name', 'Archived title');
    }

    public function test_version_conflict_batch_conversion_gate_and_bounded_search_are_enforced(): void
    {
        $fixture = $this->fixture();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $fixture['merchant']->id]);
        $created = $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $this->productPayload($unit, 'VERSION-1', '1111111111111'))->assertCreated();
        $url = $this->productsUrl($fixture['merchant'], $fixture['store']).'/'.$created->json('data.id');

        $this->actingAs($fixture['owner'])->patchJson($url, ['expected_structure_version' => 1, 'batch_tracking_enabled' => true])->assertConflict();
        $this->actingAs($fixture['owner'])->patchJson($url, ['expected_structure_version' => 1, 'batch_tracking_enabled' => true, 'batch_conversion_confirmed' => true])->assertOk();
        $this->actingAs($fixture['owner'])->patchJson($url, ['expected_structure_version' => 1, 'name' => 'Stale edit'])->assertConflict();
        $this->actingAs($fixture['cashier'])->getJson($this->productsUrl($fixture['merchant'], $fixture['store']).'?page_size=101')->assertUnprocessable();
    }

    public function test_product_images_are_validated_and_stored_in_tenant_paths(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $fixture['merchant']->id]);
        $created = $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $this->productPayload($unit, 'IMAGE-1', '2222222222222'))->assertCreated();
        $url = $this->productsUrl($fixture['merchant'], $fixture['store']).'/'.$created->json('data.id').'/images';
        $response = $this->actingAs($fixture['owner'])->post($url, ['image' => UploadedFile::fake()->image('tea.png', 800, 800), 'alt_text' => 'Tea box'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.mime_type', 'image/png');

        $image = $fixture['merchant']->getAttribute('public_id');
        $stored = Storage::disk('local')->allFiles("merchants/{$image}/catalog");
        $this->assertCount(1, $stored);
        $this->assertStringNotContainsString(Storage::disk('local')->path($stored[0]), $response->getContent());

        $productImage = ProductImage::query()->sole();
        $this->actingAs($fixture['cashier'])->get($url.'/'.$productImage->public_id)->assertOk()->assertHeader('content-type', 'image/png');
        $other = $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $this->productPayload($unit, 'IMAGE-2', '2222222222223'))->assertCreated();
        $this->actingAs($fixture['cashier'])->get($this->productsUrl($fixture['merchant'], $fixture['store']).'/'.$other->json('data.id').'/images/'.$productImage->public_id)->assertNotFound();
    }

    public function test_product_has_exactly_one_default_variant_and_never_exposes_internal_stock_ids(): void
    {
        $fixture = $this->fixture();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $fixture['merchant']->id]);
        $stockProduct = $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $this->productPayload($unit, 'STOCK-1', '3333333333333'))->assertCreated();
        $payload = $this->productPayload($unit, 'MULTI-1', '4444444444444');
        $payload['variants'][] = ['name' => 'Other', 'sku' => 'MULTI-2', 'base_unit_id' => $unit->public_id, 'is_default' => true];
        $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $payload)->assertUnprocessable();

        unset($payload['variants'][0]['is_default'], $payload['variants'][1]['is_default']);
        $payload['extra_groups'] = [['name' => 'Add-on', 'minimum_selected' => 0, 'maximum_selected' => 1, 'options' => [[
            'name' => 'Stocked add-on', 'price_delta' => '25.00', 'stock_variant_id' => $stockProduct->json('data.variants.0.id'), 'stock_quantity_base' => '1.000000',
        ]]]];
        $created = $this->actingAs($fixture['owner'])->postJson($this->productsUrl($fixture['merchant'], $fixture['store']), $payload)->assertCreated();
        $this->assertTrue($created->json('data.variants.0.is_default'));
        $this->assertFalse($created->json('data.variants.1.is_default'));
        $this->assertSame($stockProduct->json('data.variants.0.id'), $created->json('data.extra_groups.0.options.0.stock_variant_id'));
        $this->assertNotSame((string) ProductVariant::query()->where('sku', 'STOCK-1')->value('id'), $created->json('data.extra_groups.0.options.0.stock_variant_id'));
    }

    /** @return array{merchant: Merchant, store: Store, otherStore: Store, owner: User, manager: User, cashier: User} */
    private function fixture(bool $independent = false): array
    {
        $merchant = $independent ? Merchant::factory()->independent()->create() : Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $otherStore = Store::factory()->for($merchant)->create();
        $users = [];
        foreach (MembershipRole::cases() as $role) {
            $user = User::factory()->create();
            $membership = MerchantMembership::factory()->for($merchant)->for($user)->create(['role' => $role]);
            foreach ([$store, $otherStore] as $assignedStore) {
                StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $assignedStore->id]);
            }
            $users[$role->value] = $user;
        }

        return ['merchant' => $merchant, 'store' => $store, 'otherStore' => $otherStore, 'owner' => $users['owner'], 'manager' => $users['manager'], 'cashier' => $users['cashier']];
    }

    /** @return array<string, mixed> */
    private function productPayload(UnitOfMeasure $unit, string $sku, string $barcode): array
    {
        return ['type' => 'standard', 'name' => 'Sample product', 'tax_classification_reference' => 'MERCHANT-TAX-REF', 'variants' => [['name' => 'Default', 'sku' => $sku, 'base_unit_id' => $unit->public_id, 'is_default' => true, 'barcodes' => [['code' => $barcode, 'kind' => 'unit', 'is_primary' => true]]]]];
    }

    private function productsUrl(Merchant $merchant, Store $store): string
    {
        return "/api/v1/merchants/{$merchant->public_id}/stores/{$store->public_id}/catalog/products";
    }
}
