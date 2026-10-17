<?php

namespace Database\Seeders;

use App\Domain\Cash\OpenCashSession;
use App\Domain\Checkout\CartWorkflow;
use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Pricing\PublishPricingVersion;
use App\Domain\Sales\FinalizeSale;
use App\Domain\Sales\PostSaleReturn;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Product;
use App\Models\ProductStoreAvailability;
use App\Models\ProductVariant;
use App\Models\Register;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportingAcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mypos_test', 403, 'Reporting acceptance data is restricted to the disposable mypos_test database.');
        if (Merchant::query()->where('slug', 'reporting-acceptance')->exists()) {
            return;
        }
        $owner = User::factory()->create(['email' => 'report-owner@example.test', 'name' => 'Reporting Acceptance Owner', 'password' => 'Report-Acceptance-2026!']);
        $merchant = Merchant::factory()->create(['name' => 'Reporting Acceptance', 'slug' => 'reporting-acceptance']);
        $store = Store::factory()->for($merchant)->create(['name' => 'Acceptance Store', 'code' => 'REPORT']);
        $membership = MerchantMembership::factory()->for($merchant)->for($owner, 'user')->create(['role' => 'owner']);
        StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $store->id]);
        $register = Register::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'name' => 'Acceptance Till']);
        $location = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'code' => 'MAIN', 'name' => 'Main shelf', 'disposition' => 'sellable']);
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'EA', 'symbol' => 'each']);
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'store_id' => null, 'name' => 'Acceptance Tea', 'type' => 'standard', 'tax_classification_reference' => 'GENERAL']);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'store_id' => null, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => 'ACCEPTANCE-TEA']);
        ProductStoreAvailability::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'product_id' => $product->id, 'is_available' => true]);
        foreach (['product_categories' => ['Tea & coffee', 'TEA', 'product_category_id'], 'product_brands' => ['Acceptance Brand', 'ACCEPT', 'product_brand_id']] as $table => [$name, $code, $column]) {
            $id = DB::table($table)->insertGetId(['public_id' => Str::uuid7()->getBytes(), 'merchant_id' => $merchant->id, 'name' => $name, 'code' => $code, 'created_at' => now(), 'updated_at' => now()]);
            $product->forceFill([$column => $id])->save();
        }
        app(PostInventoryValuation::class)->inbound($store, $variant, InventoryCostEntryKind::AdjustmentIn, '100.000000', '40.000000', 'acceptance_opening', 'acceptance-opening', 'main', now()->toAtomString(), fn (string $cost, InventoryCostState $state) => app(PostStockMovement::class)->handle(new StockMovementCommand(location: $location, variant: $variant, kind: InventoryMovementKind::OpeningBalance, quantityBase: '100.000000', sourceType: 'acceptance_opening', sourceReference: 'acceptance-opening', unitCost: $cost, costState: $state, actor: $owner))['movement']);
        app(PublishPricingVersion::class)->handle($owner, $merchant, $store, ['scope' => 'merchant', 'expected_version' => 0, 'mode' => 'manual', 'effective_from' => now()->subMinute()->toAtomString(), 'prices' => [['variant_id' => $variant->public_id, 'unit_price' => '100.0000', 'tax_rate_percent' => '18.000000', 'tax_inclusive' => false]], 'rules' => []]);
        $session = app(OpenCashSession::class)->handle($merchant, $store, $register, $owner, (string) Str::uuid(), '1000.00', now()->toAtomString())['session'];
        for ($i = 0; $i < 30; $i++) {
            $cart = app(CartWorkflow::class)->create($merchant, $store, $register, $location, $owner);
            $cart = app(CartWorkflow::class)->replace($cart, $owner, ['expected_version' => 1, 'lines' => [['variant_id' => $variant->public_id, 'quantity' => '2.000000']]]);
            $quote = app(CartWorkflow::class)->quote($merchant, $store, $cart, $owner, $cart->version)['quote'];
            $posted = app(FinalizeSale::class)->handle($merchant, $store, $register, $owner, (string) Str::uuid(), ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote->public_id, 'quote_version' => $quote->quote_version, 'register_session_id' => $session->public_id, 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '250.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '14.00']);
            if ($i === 0) {
                $sale = $posted['sale'];
                app(PostSaleReturn::class)->handle($merchant, $store, $register, $sale, $owner, (string) Str::uuid(), ['register_session_id' => $session->public_id, 'reason' => 'Acceptance fixture partial return', 'lines' => [['sale_line_id' => $sale->lines->first()->public_id, 'quantity' => '0.500000', 'disposition' => 'restock']], 'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '59.00', 'confirmation_source' => 'not_applicable']]]);
            }
        }
    }
}
