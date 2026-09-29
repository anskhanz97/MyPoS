<?php

namespace Tests\Feature;

use App\Domain\Inventory\BatchExpiryPolicy;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\ReverseStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Inventory\ValidateBatchSelectionSnapshot;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\BatchSelectionSnapshot;
use App\Models\BatchStockBalance;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class BatchTrackingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_fefo_uses_same_day_batch_then_next_expiry_and_blocks_expired_or_short_stock(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00 UTC');
        $fixture = $this->fixture(tracked: true);
        $today = $this->batch($fixture, 'TODAY', '2026-10-01', '5.000000');
        $future = $this->batch($fixture, 'FUTURE', '2026-10-05', '5.000000');
        $expired = $this->batch($fixture, 'EXPIRED', '2026-09-30', '5.000000');
        $this->actingAs($fixture['manager'])->getJson($this->inventoryUrl($fixture).'/batches?variant_id='.$fixture['variant']->public_id)
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonFragment(['code' => 'EXPIRED', 'status' => 'expired'])
            ->assertJsonPath('meta.expiry_boundary', 'sellable_through_expiry_date');
        $createPayload = ['variant_id' => $fixture['variant']->public_id, 'code' => 'NEW-LOT', 'expiry_date' => '2026-10-20', 'received_on' => '2026-10-01'];
        $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/batches', $createPayload)
            ->assertCreated()->assertJsonPath('replayed', false)->assertJsonPath('data.status', 'expiring_soon');
        $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/batches', $createPayload)
            ->assertOk()->assertJsonPath('replayed', true);

        $response = $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/batch-selections', [
            'location_id' => $fixture['location']->public_id, 'variant_id' => $fixture['variant']->public_id,
            'quantity' => '7', 'strategy' => 'fefo',
        ])->assertCreated()->assertJsonPath('data.business_date', '2026-10-01')->assertJsonPath('data.reserved', false)->assertJsonCount(2, 'data.allocations');
        $this->assertSame($today->public_id, $response->json('data.allocations.0.batch_id'));
        $this->assertSame('5.000000', $response->json('data.allocations.0.quantity_base'));
        $this->assertSame($future->public_id, $response->json('data.allocations.1.batch_id'));
        $this->assertSame('2.000000', $response->json('data.allocations.1.quantity_base'));

        $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/batch-selections', [
            'location_id' => $fixture['location']->public_id, 'variant_id' => $fixture['variant']->public_id,
            'quantity' => '1', 'strategy' => 'manual', 'manual_allocations' => [['batch_id' => $expired->public_id, 'quantity' => '1']],
        ])->assertConflict();
        $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/batch-selections', [
            'location_id' => $fixture['location']->public_id, 'variant_id' => $fixture['variant']->public_id,
            'quantity' => '11', 'strategy' => 'fefo',
        ])->assertConflict();
    }

    public function test_expiry_boundary_and_batch_shortage_are_hard_sale_blocks(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 18:59:00 UTC');
        $fixture = $this->fixture(tracked: true);
        $batch = $this->batch($fixture, 'BOUNDARY', '2026-10-01', '2.000000');
        $policy = app(BatchExpiryPolicy::class);
        $this->assertFalse($policy->isExpired($batch, $fixture['store']));
        $sale = app(PostStockMovement::class)->handle(new StockMovementCommand(
            $fixture['location'], $fixture['variant'], InventoryMovementKind::Sale, '-2.000000', 'sale', 'SALE-BOUNDARY', batch: $batch,
        ));
        $this->assertSame('0.000000', $sale['batch_balance']?->quantity_base);

        CarbonImmutable::setTestNow('2026-10-01 19:01:00 UTC');
        $this->assertTrue($policy->isExpired($batch, $fixture['store']));
        try {
            app(PostStockMovement::class)->handle(new StockMovementCommand(
                $fixture['location'], $fixture['variant'], InventoryMovementKind::Sale, '-1.000000', 'sale', 'SALE-EXPIRED', batch: $batch,
            ));
            $this->fail('Expired stock was sold.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('expired', $exception->getMessage());
        }
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_manual_selection_rejects_batch_from_another_product_or_store(): void
    {
        $fixture = $this->fixture(tracked: true);
        $this->batch($fixture, 'LOCAL', now()->addDays(10)->toDateString(), '3.000000');
        $otherProduct = Product::factory()->create(['merchant_id' => $fixture['merchant']->id, 'batch_tracking_enabled' => true]);
        $otherVariant = ProductVariant::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => null, 'product_id' => $otherProduct->id, 'base_unit_id' => $fixture['unit']->id, 'sku' => 'OTHER-BATCH']);
        $otherBatch = ProductBatch::query()->create(['merchant_id' => $fixture['merchant']->id, 'product_variant_id' => $otherVariant->id, 'origin_store_id' => $fixture['store']->id, 'code' => 'WRONG-PRODUCT', 'expiry_date' => now()->addDays(20)->toDateString()]);
        $otherLocation = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['otherStore']->id, 'code' => 'OTHER', 'name' => 'Other', 'disposition' => 'sellable']);
        app(PostStockMovement::class)->handle(new StockMovementCommand($otherLocation, $fixture['variant'], InventoryMovementKind::PurchaseReceipt, '3.000000', 'receipt', 'OTHER-STORE', batch: $fixture['localBatch'] ?? $this->batchRecord($fixture, 'REMOTE', now()->addDays(12)->toDateString())));

        foreach ([$otherBatch->public_id, $this->batchRecord($fixture, 'REMOTE', now()->addDays(12)->toDateString())->public_id] as $batchId) {
            $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/batch-selections', [
                'location_id' => $fixture['location']->public_id, 'variant_id' => $fixture['variant']->public_id, 'quantity' => '1', 'strategy' => 'manual',
                'manual_allocations' => [['batch_id' => $batchId, 'quantity' => '1']],
            ])->assertConflict();
        }
    }

    public function test_existing_stock_requires_exact_controlled_conversion_and_retry_is_idempotent(): void
    {
        $fixture = $this->fixture(tracked: false);
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['location'], $fixture['variant'], InventoryMovementKind::OpeningBalance, '10.000000', 'opening', 'OPEN-10'));
        $catalogUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/catalog/products/{$fixture['product']->public_id}";
        $this->actingAs($fixture['manager'])->patchJson($catalogUrl, ['expected_structure_version' => 1, 'batch_tracking_enabled' => true, 'batch_conversion_confirmed' => true])->assertConflict();
        $payload = [
            'idempotency_key' => '00000000-0000-7000-8000-000000000151', 'product_id' => $fixture['product']->public_id, 'expected_structure_version' => 1,
            'allocations' => [
                ['location_id' => $fixture['location']->public_id, 'variant_id' => $fixture['variant']->public_id, 'batch_code' => 'A', 'expiry_date' => '2026-12-01', 'quantity' => '4'],
                ['location_id' => $fixture['location']->public_id, 'variant_id' => $fixture['variant']->public_id, 'batch_code' => 'B', 'expiry_date' => '2027-01-01', 'quantity' => '6'],
            ],
        ];
        $url = $this->inventoryUrl($fixture).'/batch-tracking-conversions';
        $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertCreated()->assertJsonPath('data.movements_posted', 3)->assertJsonPath('data.replayed', false);
        $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertOk()->assertJsonPath('data.replayed', true);
        $changedPayload = $payload;
        $changedPayload['allocations'][0]['quantity'] = '5';
        $this->actingAs($fixture['manager'])->postJson($url, $changedPayload)->assertConflict();
        $this->assertTrue($fixture['product']->refresh()->batch_tracking_enabled);
        $this->assertSame('10.000000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame('10.000000', number_format((float) BatchStockBalance::query()->sum('quantity_base'), 6, '.', ''));
        $this->assertSame(4, StockMovement::query()->count());
    }

    public function test_transfer_return_reversal_and_offline_snapshot_preserve_batch_identity(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00 UTC');
        $fixture = $this->fixture(tracked: true);
        $batch = $this->batch($fixture, 'TRACE', '2026-10-01', '6.000000');
        $destination = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['otherStore']->id, 'code' => 'DEST', 'name' => 'Destination', 'disposition' => 'sellable']);
        $quarantine = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'QUAR', 'name' => 'Quarantine', 'disposition' => 'quarantine']);
        $out = app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['location'], $fixture['variant'], InventoryMovementKind::TransferOut, '-2.000000', 'transfer', 'TR-1', batch: $batch))['movement'];
        app(PostStockMovement::class)->handle(new StockMovementCommand($destination, $fixture['variant'], InventoryMovementKind::TransferIn, '2.000000', 'transfer', 'TR-1-IN', batch: $batch));
        app(PostStockMovement::class)->handle(new StockMovementCommand($quarantine, $fixture['variant'], InventoryMovementKind::Return, '1.000000', 'return', 'RET-1', batch: $batch));
        app(ReverseStockMovement::class)->handle($out, 'TR-REV', 'Reverse shipment', $fixture['manager']);
        $this->assertSame(5, StockMovement::query()->where('product_batch_id', $batch->id)->count());
        $this->assertSame('6.000000', BatchStockBalance::query()->where('inventory_location_id', $fixture['location']->id)->sole()->quantity_base);

        BatchStockBalance::query()->where('inventory_location_id', $fixture['location']->id)->update(['quantity_base' => '99.000000']);
        $this->actingAs($fixture['manager'])->getJson($this->inventoryUrl($fixture).'/reconciliation')
            ->assertOk()->assertJsonPath('data.status', 'discrepancy')->assertJsonCount(1, 'data.batch_discrepancies');
        $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/reconciliation', ['confirm' => true, 'reason' => 'Repair batch projection drift'])
            ->assertOk()->assertJsonPath('data.batch_repaired', 1)->assertJsonPath('data.status', 'in_sync');
        $this->assertSame('6.000000', BatchStockBalance::query()->where('inventory_location_id', $fixture['location']->id)->sole()->quantity_base);

        $snapshotResponse = $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/batch-selections', [
            'location_id' => $fixture['location']->public_id, 'variant_id' => $fixture['variant']->public_id, 'quantity' => '1', 'strategy' => 'fefo',
        ])->assertCreated();
        $snapshot = (new BatchSelectionSnapshot)->resolveRouteBinding((string) $snapshotResponse->json('data.id'));
        $this->assertInstanceOf(BatchSelectionSnapshot::class, $snapshot);
        $snapshot->update(['expires_at' => now()->addDay()]);
        CarbonImmutable::setTestNow('2026-10-01 19:01:00 UTC');
        $this->expectException(ConflictHttpException::class);
        app(ValidateBatchSelectionSnapshot::class)->handle($snapshot->refresh(), $fixture['store']);
    }

    /** @return array<string, mixed> */
    private function fixture(bool $tracked): array
    {
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create(['timezone' => 'Asia/Karachi']);
        $otherStore = Store::factory()->for($merchant)->create(['timezone' => 'Asia/Karachi']);
        $manager = User::factory()->create();
        $membership = MerchantMembership::factory()->for($merchant)->for($manager)->create(['role' => MembershipRole::Manager]);
        foreach ([$store, $otherStore] as $assignedStore) {
            StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $assignedStore->id]);
        }
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'PIECE', 'symbol' => 'pc']);
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'store_id' => null, 'name' => 'Tracked item', 'batch_tracking_enabled' => $tracked]);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'store_id' => null, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => 'BATCH-001']);
        $location = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable', 'is_default' => true]);

        return compact('merchant', 'store', 'otherStore', 'manager', 'unit', 'product', 'variant', 'location');
    }

    /** @param array<string, mixed> $fixture */
    private function batch(array $fixture, string $code, string $expiry, string $quantity): ProductBatch
    {
        $batch = $this->batchRecord($fixture, $code, $expiry);
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['location'], $fixture['variant'], InventoryMovementKind::PurchaseReceipt, $quantity, 'receipt', 'REC-'.$code, batch: $batch));

        return $batch;
    }

    /** @param array<string, mixed> $fixture */
    private function batchRecord(array $fixture, string $code, string $expiry): ProductBatch
    {
        return ProductBatch::query()->firstOrCreate(
            ['merchant_id' => $fixture['merchant']->id, 'product_variant_id' => $fixture['variant']->id, 'code' => $code],
            ['origin_store_id' => $fixture['store']->id, 'expiry_date' => $expiry],
        );
    }

    /** @param array<string, mixed> $fixture */
    private function inventoryUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/inventory";
    }
}
