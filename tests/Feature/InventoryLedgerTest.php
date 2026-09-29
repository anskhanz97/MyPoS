<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryEffect;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\ReverseStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\BundleComponent;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SecurityAuditEvent;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_adjustments_convert_to_base_units_are_idempotent_and_allow_visible_negative_stock(): void
    {
        $fixture = $this->fixture();
        $location = $this->createLocation($fixture);
        $reason = $this->createReason($fixture);
        $carton = UnitOfMeasure::factory()->create(['merchant_id' => $fixture['merchant']->id, 'code' => 'CARTON', 'symbol' => 'ctn']);
        UnitConversion::query()->create(['merchant_id' => $fixture['merchant']->id, 'product_variant_id' => $fixture['variant']->id, 'from_unit_id' => $carton->id, 'factor_to_base' => '12.000000']);
        $idempotencyKey = '00000000-0000-7000-8000-000000000141';
        $payload = ['idempotency_key' => $idempotencyKey, 'location_id' => $location->json('data.id'), 'variant_id' => $fixture['variant']->public_id, 'unit_id' => $carton->public_id, 'quantity' => '2', 'reason_id' => $reason->json('data.id'), 'reason_text' => 'Opening shelf count'];

        $this->actingAs($fixture['manager'])->postJson($this->adjustmentsUrl($fixture), $payload)
            ->assertCreated()->assertJsonPath('data.movement.quantity_base', '24.000000')->assertJsonPath('data.balance.quantity_base', '24.000000')->assertJsonPath('data.replayed', false);
        $this->actingAs($fixture['manager'])->postJson($this->adjustmentsUrl($fixture), $payload)
            ->assertOk()->assertJsonPath('data.replayed', true);
        $this->actingAs($fixture['manager'])->postJson($this->adjustmentsUrl($fixture), [...$payload, 'quantity' => '3'])
            ->assertConflict();

        $negativePayload = [...$payload, 'idempotency_key' => '00000000-0000-7000-8000-000000000142', 'unit_id' => $fixture['unit']->public_id, 'quantity' => '-30', 'reason_text' => 'Verified count decrease'];
        $this->actingAs($fixture['manager'])->postJson($this->adjustmentsUrl($fixture), $negativePayload)
            ->assertCreated()->assertJsonPath('data.balance.quantity_base', '-6.000000')->assertJsonPath('data.balance.is_negative', true);
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertSame(2, SecurityAuditEvent::query()->where('event_code', 'inventory.adjustment.posted')->count());

        $this->actingAs($fixture['cashier'])->getJson($this->inventoryUrl($fixture).'/balances?negative_only=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.quantity_base', '-6.000000');
        $this->actingAs($fixture['cashier'])->getJson($this->inventoryUrl($fixture).'/movements?source_reference='.$idempotencyKey)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.source.reference', $idempotencyKey);
        $this->actingAs($fixture['cashier'])->postJson($this->adjustmentsUrl($fixture), $negativePayload)->assertForbidden();
    }

    public function test_independent_product_and_location_cannot_cross_store_boundaries(): void
    {
        $fixture = $this->fixture(independent: true);
        $location = $this->createLocation($fixture);
        $reason = $this->createReason($fixture);
        $otherLocation = $this->actingAs($fixture['manager'])->postJson($this->locationsUrl($fixture, $fixture['otherStore']), ['code' => 'OTHER', 'name' => 'Other stock', 'disposition' => 'sellable'])->assertCreated();
        $payload = ['idempotency_key' => '00000000-0000-7000-8000-000000000143', 'location_id' => $otherLocation->json('data.id'), 'variant_id' => $fixture['variant']->public_id, 'unit_id' => $fixture['unit']->public_id, 'quantity' => '1', 'reason_id' => $reason->json('data.id'), 'reason_text' => 'Invalid cross-store movement'];
        $this->actingAs($fixture['manager'])->postJson($this->adjustmentsUrl($fixture, $fixture['otherStore']), $payload)->assertNotFound();
        $this->actingAs($fixture['manager'])->postJson($this->adjustmentsUrl($fixture, $fixture['otherStore']), [...$payload, 'location_id' => $location->json('data.id')])->assertNotFound();
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_adjustment_reason_can_require_a_bound_single_use_approval(): void
    {
        $fixture = $this->fixture();
        $location = $this->createLocation($fixture);
        $reason = $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/adjustment-reasons', [
            'code' => 'APPROVED_COUNT',
            'name' => 'Approved count correction',
            'direction' => 'both',
            'requires_approval' => true,
        ])->assertCreated();
        $payload = [
            'idempotency_key' => '00000000-0000-7000-8000-000000000144',
            'location_id' => $location->json('data.id'),
            'variant_id' => $fixture['variant']->public_id,
            'unit_id' => $fixture['unit']->public_id,
            'quantity' => '1',
            'reason_id' => $reason->json('data.id'),
            'reason_text' => 'Count verified by supervisor',
        ];

        $this->actingAs($fixture['manager'])->postJson($this->adjustmentsUrl($fixture), $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_approval');
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, StockBalance::query()->count());
    }

    public function test_reconciliation_exposes_projection_drift_and_repairs_only_after_explicit_confirmation(): void
    {
        $fixture = $this->fixture();
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable', 'is_default' => true]);
        $post = app(PostStockMovement::class);
        $movement = $post->handle(new StockMovementCommand($location, $fixture['variant'], InventoryMovementKind::OpeningBalance, '8.500000', 'opening_balance', 'OPEN-1'))['movement'];
        StockBalance::query()->update(['quantity_base' => '99.000000', 'movement_count' => 7]);

        $this->actingAs($fixture['manager'])->getJson($this->inventoryUrl($fixture).'/reconciliation')
            ->assertOk()->assertJsonPath('data.status', 'discrepancy')->assertJsonPath('data.discrepancies.0.ledger_quantity', '8.500000')->assertJsonPath('data.discrepancies.0.projected_quantity', '99.000000');
        $this->assertSame('99.000000', StockBalance::query()->sole()->quantity_base);
        $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/reconciliation', ['confirm' => true, 'reason' => 'Projection drift test'])
            ->assertOk()->assertJsonPath('data.repaired', 1)->assertJsonPath('data.status', 'in_sync');
        $this->assertSame('8.500000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame(1, StockMovement::query()->count());

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_reversal_is_append_only_and_cannot_be_applied_twice(): void
    {
        $fixture = $this->fixture();
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable', 'is_default' => true]);
        $movement = app(PostStockMovement::class)->handle(new StockMovementCommand($location, $fixture['variant'], InventoryMovementKind::OpeningBalance, '5.000000', 'opening_balance', 'OPEN-REV'))['movement'];
        $reversal = app(ReverseStockMovement::class)->handle($movement, 'REV-1', 'Correct duplicate opening', $fixture['manager']);
        $this->assertSame('-5.000000', $reversal->quantity_base);
        $this->assertSame('0.000000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame(2, StockMovement::query()->count());

        $this->expectException(ConflictHttpException::class);
        app(ReverseStockMovement::class)->handle($movement, 'REV-2', 'Duplicate reversal', $fixture['manager']);
    }

    public function test_fixed_bundle_effect_expands_leaf_finished_products_exactly_once(): void
    {
        $fixture = $this->fixture();
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable', 'is_default' => true]);
        $second = ProductVariant::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => null, 'base_unit_id' => $fixture['unit']->id, 'sku' => 'SECOND']);
        $bundleProduct = Product::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => null, 'type' => ProductType::Bundle]);
        $bundle = ProductVariant::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => null, 'product_id' => $bundleProduct->id, 'base_unit_id' => $fixture['unit']->id, 'sku' => 'BUNDLE']);
        BundleComponent::query()->create(['merchant_id' => $fixture['merchant']->id, 'bundle_variant_id' => $bundle->id, 'component_variant_id' => $fixture['variant']->id, 'quantity_base' => '3.000000']);
        BundleComponent::query()->create(['merchant_id' => $fixture['merchant']->id, 'bundle_variant_id' => $bundle->id, 'component_variant_id' => $second->id, 'quantity_base' => '1.000000']);

        $poster = app(PostInventoryEffect::class);
        $poster->handle($location, $bundle, InventoryMovementKind::Sale, '-2.000000', 'sale', 'SALE-1', 'LINE-1', $fixture['cashier']);
        $poster->handle($location, $bundle, InventoryMovementKind::Sale, '-2.000000', 'sale', 'SALE-1', 'LINE-1', $fixture['cashier']);
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertSame('-6.000000', StockBalance::query()->where('product_variant_id', $fixture['variant']->id)->sole()->quantity_base);
        $this->assertSame('-2.000000', StockBalance::query()->where('product_variant_id', $second->id)->sole()->quantity_base);
    }

    /** @return array{merchant: Merchant, store: Store, otherStore: Store, owner: User, manager: User, cashier: User, unit: UnitOfMeasure, variant: ProductVariant} */
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
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'PIECE', 'symbol' => 'pc']);
        $scopeStoreId = $independent ? $store->id : null;
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $scopeStoreId, 'name' => 'Inventory item']);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $scopeStoreId, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => 'INV-001', 'name' => 'Default']);

        return ['merchant' => $merchant, 'store' => $store, 'otherStore' => $otherStore, 'owner' => $users['owner'], 'manager' => $users['manager'], 'cashier' => $users['cashier'], 'unit' => $unit, 'variant' => $variant];
    }

    /** @param array{merchant: Merchant, store: Store, manager: User} $fixture */
    private function createLocation(array $fixture): TestResponse
    {
        return $this->actingAs($fixture['manager'])->postJson($this->locationsUrl($fixture), ['code' => 'MAIN', 'name' => 'Main sellable stock', 'disposition' => 'sellable'])->assertCreated();
    }

    /** @param array{merchant: Merchant, store: Store, manager: User} $fixture */
    private function createReason(array $fixture): TestResponse
    {
        return $this->actingAs($fixture['manager'])->postJson($this->inventoryUrl($fixture).'/adjustment-reasons', ['code' => 'COUNT', 'name' => 'Verified count', 'direction' => 'both'])->assertCreated();
    }

    /** @param array{merchant: Merchant, store: Store} $fixture */
    private function inventoryUrl(array $fixture, ?Store $store = null): string
    {
        $activeStore = $store ?? $fixture['store'];

        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$activeStore->public_id}/inventory";
    }

    /** @param array{merchant: Merchant, store: Store} $fixture */
    private function locationsUrl(array $fixture, ?Store $store = null): string
    {
        return $this->inventoryUrl($fixture, $store).'/locations';
    }

    /** @param array{merchant: Merchant, store: Store} $fixture */
    private function adjustmentsUrl(array $fixture, ?Store $store = null): string
    {
        return $this->inventoryUrl($fixture, $store).'/adjustments';
    }
}
