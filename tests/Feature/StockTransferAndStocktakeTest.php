<?php

namespace Tests\Feature;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\BatchStockBalance;
use App\Models\InventoryCostBalance;
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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StockTransferAndStocktakeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shared_batch_transfer_dispatch_and_partial_short_receipt_are_idempotent(): void
    {
        $fixture = $this->fixture(tracked: true);
        $batch = ProductBatch::query()->create(['merchant_id' => $fixture['merchant']->id, 'product_variant_id' => $fixture['sourceVariant']->id, 'origin_store_id' => $fixture['source']->id, 'code' => 'LOT-SHARED', 'expiry_date' => now()->addMonth()->toDateString()]);
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['sourceLocation'], $fixture['sourceVariant'], InventoryMovementKind::OpeningBalance, '10.000000', 'opening', 'OPEN-TR', batch: $batch));
        $transfer = $this->createTransfer($fixture, '6', $batch);
        $transferId = $transfer->json('data.id');
        $lineId = $transfer->json('data.lines.0.id');
        $url = $this->transferUrl($fixture).'/'.$transferId;
        $dispatch = ['idempotency_key' => '00000000-0000-7000-8000-000000000161', 'expected_version' => 1];
        $this->actingAs($fixture['manager'])->postJson($url.'/dispatch', $dispatch)->assertCreated()->assertJsonPath('data.state', 'dispatched')->assertJsonPath('data.lines.0.expected_destination_quantity_base', '6.000000');
        $this->actingAs($fixture['manager'])->postJson($url.'/dispatch', $dispatch)->assertOk()->assertJsonPath('replayed', true);
        $this->actingAs($fixture['manager'])->postJson($url.'/dispatch', array_replace($dispatch, ['expected_version' => 2]))->assertConflict();
        $this->assertSame('4.000000', BatchStockBalance::query()->where('store_id', $fixture['source']->id)->sole()->quantity_base);

        $first = ['idempotency_key' => '00000000-0000-7000-8000-000000000162', 'expected_version' => 2, 'resolution' => 'partial', 'lines' => [['transfer_line_id' => $lineId, 'quantity_destination_base' => '2']]];
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', $first)->assertCreated()->assertJsonPath('data.transfer.state', 'partially_received')->assertJsonPath('data.transfer.lines.0.in_transit_destination_quantity_base', '4.000000');
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', $first)->assertOk()->assertJsonPath('replayed', true);
        $second = ['idempotency_key' => '00000000-0000-7000-8000-000000000163', 'expected_version' => 3, 'resolution' => 'short_close', 'reason' => 'One unit missing in transit', 'lines' => [['transfer_line_id' => $lineId, 'quantity_destination_base' => '3']]];
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', $second)->assertCreated()->assertJsonPath('data.transfer.state', 'received')->assertJsonPath('data.transfer.lines.0.short_destination_quantity_base', '1.000000')->assertJsonPath('data.transfer.lines.0.in_transit_destination_quantity_base', '0.000000');
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', array_replace($second, ['expected_version' => 4]))->assertConflict();
        $this->assertSame('5.000000', BatchStockBalance::query()->where('store_id', $fixture['destination']->id)->sole()->quantity_base);
        $this->assertSame(4, StockMovement::query()->count());
    }

    public function test_independent_transfer_requires_explicit_mapping_and_converts_units_and_batch_identity(): void
    {
        $fixture = $this->fixture(tracked: true, independent: true);
        $batch = ProductBatch::query()->create(['merchant_id' => $fixture['merchant']->id, 'product_variant_id' => $fixture['sourceVariant']->id, 'origin_store_id' => $fixture['source']->id, 'code' => 'LOT-MAP', 'expiry_date' => now()->addMonths(2)->toDateString()]);
        app(PostInventoryValuation::class)->inbound(
            $fixture['source'], $fixture['sourceVariant'], InventoryCostEntryKind::AdjustmentIn, '3.000000', '120.000000',
            'opening', 'OPEN-MAP', 'main', now()->toIso8601String(),
            fn (string $unitCost, InventoryCostState $state) => app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['sourceLocation'], $fixture['sourceVariant'], InventoryMovementKind::OpeningBalance, '3.000000', 'opening', 'OPEN-MAP', batch: $batch, unitCost: $unitCost, costState: $state))['movement'],
        );
        $payload = $this->transferPayload($fixture, '2', $batch);
        $this->actingAs($fixture['manager'])->postJson($this->transferUrl($fixture), $payload)->assertConflict();
        $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/variant-mappings', [
            'destination_store_id' => $fixture['destination']->public_id, 'source_variant_id' => $fixture['sourceVariant']->public_id,
            'destination_variant_id' => $fixture['destinationVariant']->public_id, 'factor_to_destination_base' => '12',
        ])->assertCreated();
        $transfer = $this->actingAs($fixture['manager'])->postJson($this->transferUrl($fixture), $payload)->assertCreated();
        $url = $this->transferUrl($fixture).'/'.$transfer->json('data.id');
        $this->actingAs($fixture['manager'])->postJson($url.'/dispatch', ['idempotency_key' => '00000000-0000-7000-8000-000000000164', 'expected_version' => 1])->assertCreated()
            ->assertJsonPath('data.lines.0.expected_destination_quantity_base', '24.000000')
            ->assertJsonPath('data.lines.0.source_unit_cost', '120.000000')
            ->assertJsonPath('data.lines.0.destination_unit_cost', '10.000000');
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', [
            'idempotency_key' => '00000000-0000-7000-8000-000000000165', 'expected_version' => 2, 'resolution' => 'complete',
            'lines' => [['transfer_line_id' => $transfer->json('data.lines.0.id'), 'quantity_destination_base' => '24']],
        ])->assertCreated()->assertJsonPath('data.transfer.state', 'received');
        $destinationBalance = BatchStockBalance::query()->where('store_id', $fixture['destination']->id)->with('batch')->sole();
        $this->assertSame('24.000000', $destinationBalance->quantity_base);
        $this->assertSame($fixture['destinationVariant']->id, $destinationBalance->product_variant_id);
        $this->assertSame('LOT-MAP', $destinationBalance->batch->code);
        $sourceCost = InventoryCostBalance::query()->where('store_id', $fixture['source']->id)->sole();
        $destinationCost = InventoryCostBalance::query()->where('store_id', $fixture['destination']->id)->sole();
        $this->assertSame('1.000000', $sourceCost->quantity_base);
        $this->assertSame('120.000000', $sourceCost->average_unit_cost);
        $this->assertSame('24.000000', $destinationCost->quantity_base);
        $this->assertSame('10.000000', $destinationCost->average_unit_cost);
        $this->assertSame('240.000000', $destinationCost->inventory_value);
    }

    public function test_expired_batch_requires_quarantine_destination_and_destination_permission(): void
    {
        $fixture = $this->fixture(tracked: true, assignDestination: false);
        $batch = ProductBatch::query()->create(['merchant_id' => $fixture['merchant']->id, 'product_variant_id' => $fixture['sourceVariant']->id, 'origin_store_id' => $fixture['source']->id, 'code' => 'OLD', 'expiry_date' => now()->subDay()->toDateString()]);
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['sourceLocation'], $fixture['sourceVariant'], InventoryMovementKind::OpeningBalance, '2.000000', 'opening', 'OPEN-OLD', batch: $batch));
        $this->actingAs($fixture['manager'])->postJson($this->transferUrl($fixture), $this->transferPayload($fixture, '1', $batch))->assertNotFound();

        StoreAssignment::query()->create(['merchant_id' => $fixture['merchant']->id, 'merchant_membership_id' => $fixture['membership']->id, 'store_id' => $fixture['destination']->id]);
        $transfer = $this->createTransfer($fixture, '1', $batch);
        $url = $this->transferUrl($fixture).'/'.$transfer->json('data.id');
        $this->actingAs($fixture['manager'])->postJson($url.'/dispatch', ['idempotency_key' => '00000000-0000-7000-8000-000000000166', 'expected_version' => 1])->assertCreated();
        $receipt = [
            'idempotency_key' => '00000000-0000-7000-8000-000000000167', 'expected_version' => 2, 'resolution' => 'complete',
            'lines' => [['transfer_line_id' => $transfer->json('data.lines.0.id'), 'quantity_destination_base' => '1']],
        ];
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', $receipt)->assertConflict();
        $this->assertSame(0, BatchStockBalance::query()->where('store_id', $fixture['destination']->id)->count());
        $fixture['destinationLocation']->update(['disposition' => 'quarantine']);
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', $receipt)->assertCreated()->assertJsonPath('data.transfer.state', 'received');
        $this->assertSame('1.000000', BatchStockBalance::query()->where('store_id', $fixture['destination']->id)->sole()->quantity_base);
    }

    public function test_excess_receipt_is_explicit_and_draft_only_cancellation_posts_no_stock(): void
    {
        $fixture = $this->fixture(tracked: false);
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['sourceLocation'], $fixture['sourceVariant'], InventoryMovementKind::OpeningBalance, '5.000000', 'opening', 'OPEN-EXCESS'));
        $cancel = $this->createTransfer($fixture, '1', null);
        $cancelUrl = $this->transferUrl($fixture).'/'.$cancel->json('data.id');
        $this->actingAs($fixture['manager'])->postJson($cancelUrl.'/cancel', ['expected_version' => 1, 'reason' => 'Transfer no longer required'])->assertOk()->assertJsonPath('data.state', 'cancelled');
        $this->actingAs($fixture['manager'])->postJson($cancelUrl.'/dispatch', ['idempotency_key' => '00000000-0000-7000-8000-000000000169', 'expected_version' => 2])->assertConflict();

        $payload = $this->transferPayload($fixture, '2', null);
        $payload['lines'][] = ['source_variant_id' => $fixture['sourceVariant']->public_id, 'source_batch_id' => null, 'quantity_base' => '2'];
        $transfer = $this->actingAs($fixture['manager'])->postJson($this->transferUrl($fixture), $payload)->assertCreated();
        $url = $this->transferUrl($fixture).'/'.$transfer->json('data.id');
        $this->actingAs($fixture['manager'])->postJson($url.'/dispatch', ['idempotency_key' => '00000000-0000-7000-8000-000000000170', 'expected_version' => 1])->assertCreated();
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', [
            'idempotency_key' => '00000000-0000-7000-8000-000000000174', 'expected_version' => 2, 'resolution' => 'excess_accept', 'reason' => 'Destination reports an incomplete mixed receipt',
            'lines' => [
                ['transfer_line_id' => $transfer->json('data.lines.0.id'), 'quantity_destination_base' => '3'],
                ['transfer_line_id' => $transfer->json('data.lines.1.id'), 'quantity_destination_base' => '0.5'],
            ],
        ])->assertConflict();
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', [
            'idempotency_key' => '00000000-0000-7000-8000-000000000171', 'expected_version' => 2, 'resolution' => 'excess_accept', 'reason' => 'Destination verified one extra unit',
            'lines' => [
                ['transfer_line_id' => $transfer->json('data.lines.0.id'), 'quantity_destination_base' => '3'],
                ['transfer_line_id' => $transfer->json('data.lines.1.id'), 'quantity_destination_base' => '2'],
            ],
        ])->assertCreated()->assertJsonPath('data.transfer.state', 'received')->assertJsonPath('data.transfer.lines.0.excess_destination_quantity_base', '1.000000');
        $this->assertSame('5.000000', StockBalance::query()->where('store_id', $fixture['destination']->id)->sole()->quantity_base);
        $this->assertSame(5, StockMovement::query()->count());
    }

    public function test_zero_quantity_short_close_records_missing_stock_without_destination_movement(): void
    {
        $fixture = $this->fixture(tracked: false);
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['sourceLocation'], $fixture['sourceVariant'], InventoryMovementKind::OpeningBalance, '2.000000', 'opening', 'OPEN-MISSING'));
        $transfer = $this->createTransfer($fixture, '2', null);
        $url = $this->transferUrl($fixture).'/'.$transfer->json('data.id');
        $this->actingAs($fixture['manager'])->postJson($url.'/dispatch', ['idempotency_key' => '00000000-0000-7000-8000-000000000172', 'expected_version' => 1])->assertCreated();
        $this->actingAs($fixture['manager'])->postJson($url.'/receipts', [
            'idempotency_key' => '00000000-0000-7000-8000-000000000173', 'expected_version' => 2, 'resolution' => 'short_close', 'reason' => 'Entire parcel missing in transit',
            'lines' => [['transfer_line_id' => $transfer->json('data.lines.0.id'), 'quantity_destination_base' => '0']],
        ])->assertCreated()->assertJsonPath('data.transfer.state', 'received')
            ->assertJsonPath('data.transfer.lines.0.short_destination_quantity_base', '2.000000')
            ->assertJsonPath('data.transfer.lines.0.in_transit_destination_quantity_base', '0.000000');
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertDatabaseMissing('stock_balances', ['store_id' => $fixture['destination']->id]);
    }

    public function test_stocktake_applies_count_time_discrepancy_without_overwriting_later_sales(): void
    {
        $fixture = $this->fixture(tracked: false);
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['sourceLocation'], $fixture['sourceVariant'], InventoryMovementKind::OpeningBalance, '10.000000', 'opening', 'COUNT-OPEN'));
        $stocktake = $this->actingAs($fixture['manager'])->postJson($this->stocktakeUrl($fixture), ['location_id' => $fixture['sourceLocation']->public_id, 'notes' => 'Cycle count'])->assertCreated();
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['sourceLocation'], $fixture['sourceVariant'], InventoryMovementKind::Sale, '-2.000000', 'sale', 'COUNT-SALE-1'));
        $stocktakeId = $stocktake->json('data.id');
        $lineId = $stocktake->json('data.lines.0.id');
        $url = $this->stocktakeUrl($fixture).'/'.$stocktakeId;
        $this->actingAs($fixture['manager'])->getJson($this->stocktakeUrl($fixture).'?state=counting')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $stocktakeId)->assertJsonPath('meta.total', 1);
        $this->actingAs($fixture['manager'])->getJson($url)
            ->assertOk()->assertJsonPath('data.lines.0.id', $lineId)
            ->assertJsonPath('data.baseline_movement_id', $stocktake->json('data.baseline_movement_id'));
        $this->actingAs($fixture['manager'])->putJson($url.'/counts', ['expected_version' => 1, 'lines' => [['stocktake_line_id' => $lineId, 'counted_quantity_base' => '7']]])
            ->assertOk()->assertJsonPath('data.lines.0.expected_at_count_quantity_base', '8.000000')->assertJsonPath('data.lines.0.discrepancy_quantity_base', '-1.000000');
        app(PostStockMovement::class)->handle(new StockMovementCommand($fixture['sourceLocation'], $fixture['sourceVariant'], InventoryMovementKind::Sale, '-1.000000', 'sale', 'COUNT-SALE-2'));
        $this->actingAs($fixture['manager'])->postJson($url.'/review', ['expected_version' => 2])->assertOk()->assertJsonPath('data.state', 'review');
        $post = ['idempotency_key' => '00000000-0000-7000-8000-000000000168', 'expected_version' => 3, 'reason' => 'Verified physical count discrepancy'];
        $this->actingAs($fixture['manager'])->postJson($url.'/post', $post)->assertCreated()->assertJsonPath('data.state', 'posted')->assertJsonPath('data.movements_posted', 1);
        $this->actingAs($fixture['manager'])->postJson($url.'/post', $post)->assertOk()->assertJsonPath('replayed', true);
        $this->actingAs($fixture['manager'])->postJson($url.'/post', array_replace($post, ['expected_version' => 4]))->assertConflict();
        $this->actingAs($fixture['manager'])->getJson($this->stocktakeUrl($fixture).'?state=counting')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame('6.000000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame(4, StockMovement::query()->count());
    }

    /** @return array<string, mixed> */
    private function fixture(bool $tracked, bool $independent = false, bool $assignDestination = true): array
    {
        $merchant = $independent ? Merchant::factory()->independent()->create() : Merchant::factory()->create();
        $source = Store::factory()->for($merchant)->create(['name' => 'Source']);
        $destination = Store::factory()->for($merchant)->create(['name' => 'Destination']);
        $manager = User::factory()->create();
        $membership = MerchantMembership::factory()->for($merchant)->for($manager)->create(['role' => MembershipRole::Manager]);
        StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $source->id]);
        if ($assignDestination) {
            StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $destination->id]);
        }
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'UNIT', 'symbol' => 'u']);
        $sourceProduct = Product::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $independent ? $source->id : null, 'batch_tracking_enabled' => $tracked, 'name' => 'Source item']);
        $sourceVariant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $independent ? $source->id : null, 'product_id' => $sourceProduct->id, 'base_unit_id' => $unit->id, 'sku' => 'SRC']);
        if ($independent) {
            $destinationProduct = Product::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $destination->id, 'batch_tracking_enabled' => $tracked, 'name' => 'Destination item']);
            $destinationVariant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $destination->id, 'product_id' => $destinationProduct->id, 'base_unit_id' => $unit->id, 'sku' => 'DST']);
        } else {
            $destinationVariant = $sourceVariant;
        }
        $sourceLocation = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $source->id, 'code' => 'SOURCE', 'name' => 'Source stock', 'disposition' => 'sellable']);
        $destinationLocation = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $destination->id, 'code' => 'DEST', 'name' => 'Destination stock', 'disposition' => 'sellable']);

        return compact('merchant', 'source', 'destination', 'manager', 'membership', 'unit', 'sourceProduct', 'sourceVariant', 'destinationVariant', 'sourceLocation', 'destinationLocation');
    }

    /** @param array<string, mixed> $fixture */
    private function transferPayload(array $fixture, string $quantity, ?ProductBatch $batch): array
    {
        return ['destination_store_id' => $fixture['destination']->public_id, 'source_location_id' => $fixture['sourceLocation']->public_id, 'destination_location_id' => $fixture['destinationLocation']->public_id, 'lines' => [['source_variant_id' => $fixture['sourceVariant']->public_id, 'source_batch_id' => $batch?->public_id, 'quantity_base' => $quantity]]];
    }

    /** @param array<string, mixed> $fixture */
    private function createTransfer(array $fixture, string $quantity, ?ProductBatch $batch): TestResponse
    {
        return $this->actingAs($fixture['manager'])->postJson($this->transferUrl($fixture), $this->transferPayload($fixture, $quantity, $batch))->assertCreated();
    }

    /** @param array<string, mixed> $fixture */
    private function inventoryUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['source']->public_id}/inventory";
    }

    /** @param array<string, mixed> $fixture */
    private function transferUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['source']->public_id}/stock-transfers";
    }

    /** @param array<string, mixed> $fixture */
    private function stocktakeUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['source']->public_id}/stocktakes";
    }
}
