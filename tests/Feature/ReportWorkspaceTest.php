<?php

namespace Tests\Feature;

use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Cash\ReviewCashSession;
use App\Domain\Cash\SubmitCashSessionCount;
use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Reporting\ReportCatalog;
use App\Domain\Reporting\ReportExporter;
use App\Domain\Reporting\ReportProjection;
use App\Domain\Reporting\ReportSetupImport;
use App\Domain\Shared\Math\Decimal;
use App\Jobs\ProcessReportExport;
use App\Models\BundleComponent;
use App\Models\CashSession;
use App\Models\DataTransferJob;
use App\Models\ExtraGroup;
use App\Models\ExtraOption;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\OperationalCustomer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsReportingFixture;
use Tests\TestCase;

class ReportWorkspaceTest extends TestCase
{
    use BuildsReportingFixture, LazilyRefreshDatabase;

    public function test_freshness_marker_stays_consistent_when_a_sale_posts_during_its_read(): void
    {
        $c = $this->soldFixture();
        $projection = app(ReportProjection::class);
        $before = $projection->watermark($c['f']['merchant']);
        $posted = false;
        DB::listen(function (QueryExecuted $event) use ($c, &$posted): void {
            if (! $posted && str_contains($event->sql, 'COUNT(*) records') && str_contains($event->sql, 'sales')) {
                $posted = true;
                $this->postReportingSale($c, [['variant_id' => $c['f']['first']->public_id, 'quantity' => '1.000000']]);
            }
        });

        $captured = $projection->watermark($c['f']['merchant']);

        $this->assertTrue($posted);
        $this->assertSame($before, $captured);
        $this->assertSame(2, DB::table('sales')->where('merchant_id', $c['f']['merchant']->id)->count());
    }

    public function test_cartons_and_base_units_remain_separate_while_their_money_reconciles(): void
    {
        $c = $this->soldFixture();
        $carton = UnitOfMeasure::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'code' => 'CTN', 'symbol' => 'ctn']);
        UnitConversion::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'product_variant_id' => $c['f']['first']->id, 'from_unit_id' => $carton->id, 'factor_to_base' => '12.000000']);
        $this->postReportingSale($c, [['variant_id' => $c['f']['first']->public_id, 'unit_id' => $carton->public_id, 'quantity' => '1.000000']]);
        app(ReportProjection::class)->rebuild($c['f']['merchant']);
        $data = $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonPath('data.pagination.total', 2)->assertJsonPath('data.summary.revenue', '300.00')->assertJsonPath('data.summary.cogs', '560.00')->assertJsonMissingPath('data.summary.quantity')->json('data');
        $this->assertCount(2, array_unique(array_column($data['rows'], 'unit')));
        $this->assertCount(2, array_unique(array_column($data['rows'], 'group_key')));
    }

    public function test_backfill_and_forced_rebuild_invalidate_cached_figures_without_new_ledger_rows(): void
    {
        $c = $this->soldFixture();
        DB::table('stock_movements')->where('merchant_id', $c['f']['merchant']->id)->whereNotNull('sale_line_id')->update(['sale_line_id' => null]);
        $projection = app(ReportProjection::class);
        $projection->rebuild($c['f']['merchant']);
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonPath('data.summary.cogs', null);
        $this->artisan('reports:backfill')->assertSuccessful();
        $this->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonPath('data.summary.cogs', '80.00');
        $this->artisan('reports:refresh', ['merchant' => $c['f']['merchant']->public_id, '--force' => true])->assertSuccessful();
        $this->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonPath('data.summary.cogs', '80.00')->assertJsonPath('data.rows.0.cogs', '80.00');
    }

    public function test_repeat_customer_rate_and_live_tail_do_not_count_walk_ins_as_identified_customers(): void
    {
        $c = $this->soldFixture();
        $alice = OperationalCustomer::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'store_id' => null, 'name' => 'Alice']);
        $bob = OperationalCustomer::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'store_id' => null, 'name' => 'Bob']);
        $lines = [['variant_id' => $c['f']['first']->public_id, 'quantity' => '1.000000']];
        $this->postReportingSale($c, $lines, $alice->public_id);
        $projection = app(ReportProjection::class);
        $projection->rebuild($c['f']['merchant']);
        $this->postReportingSale($c, $lines, $alice->public_id);
        $this->postReportingSale($c, $lines, $bob->public_id);
        $this->assertNotNull($projection->readWindow($c['f']['merchant']));
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/repeat-customer-rate'.$c['range'])->assertOk()->assertJsonPath('data.summary.repeat_customer_rate', '50.00')->assertJsonPath('data.summary.transactions', 3)->assertJsonPath('data.summary.revenue', '300.00');
        $this->getJson($c['reportBase'].'/reports/sales-summary'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '500.00')->assertJsonPath('data.summary.transactions', 4);
        $this->getJson($c['reportBase'].'/reports/stock-levels'.$c['range'])->assertOk()->assertJsonPath('data.rows.0.last_sale', substr($c['session']['business_date'], 0, 10));
    }

    public function test_mixed_tax_rates_cart_discount_and_partial_return_reconcile(): void
    {
        $c = $this->soldFixture();
        $prices = $this->pricingPayload($c['f']);
        $prices['expected_version'] = 1;
        $prices['prices'][1]['tax_rate_percent'] = '0.000000';
        $this->actingAs($c['f']['owner'])->postJson($this->pricingUrl($c['f']).'/versions', $prices)->assertCreated();
        $sale = $this->postReportingSale($c, [['variant_id' => $c['f']['first']->public_id, 'quantity' => '1.000000'], ['variant_id' => $c['f']['second']->public_id, 'quantity' => '1.000000']], cartDiscount: '1.00');
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/tax-report'.$c['range'])->assertOk()->assertJsonPath('data.pagination.total', 2)->assertJsonPath('data.summary.revenue', '349.00')->assertJsonPath('data.summary.tax', '53.88');
        $this->actingAs($c['f']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales/'.$sale['id'].'/returns', ['register_session_id' => $c['session']['id'], 'reason' => 'Mixed tax return', 'lines' => [['sale_line_id' => $sale['lines'][0]['id'], 'quantity' => '0.500000', 'disposition' => 'restock']], 'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '58.61', 'confirmation_source' => 'not_applicable']]])->assertCreated();
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/tax-report'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '299.33')->assertJsonPath('data.summary.tax', '44.94')->assertJsonPath('data.summary.sales_including_tax', '344.27');
        app(ReportProjection::class)->rebuild($c['f']['merchant']);
        $this->getJson($c['reportBase'].'/reports/top-bottom-sellers'.$c['range'])->assertOk()->assertJsonPath('data.rows.0.sku', 'TEA-1');
        $this->getJson($c['reportBase'].'/reports/top-bottom-sellers'.$c['range'].'&direction=asc')->assertOk()->assertJsonPath('data.rows.0.sku', 'TEA-2');
    }

    public function test_projection_rejects_late_committing_ids_and_old_document_cost_changes(): void
    {
        $c = $this->soldFixture();
        $sale = (array) DB::table('sales')->where('merchant_id', $c['f']['merchant']->id)->first();
        $line = (array) DB::table('sale_lines')->where('sale_id', $sale['id'])->first();
        $high = $sale['id'] + 2;
        unset($sale['id'], $line['id']);
        $clone = function (int $id) use ($sale, $line): void {
            DB::table('sales')->insert(array_replace($sale, ['id' => $id, 'public_id' => Uuid::uuid7()->getBytes(), 'number' => 'LATE-'.$id, 'client_transaction_id' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid()]));
            DB::table('sale_lines')->insert(array_replace($line, ['sale_id' => $id, 'public_id' => Uuid::uuid7()->getBytes()]));
        };
        $clone($high);
        $projection = app(ReportProjection::class);
        $projection->rebuild($c['f']['merchant']);
        $clone($high - 1);
        $this->assertNull($projection->readWindow($c['f']['merchant']));
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '600.00');
        $projection->rebuild($c['f']['merchant']);
        $reference = $c['sale']['lines'][0]['id'].':late-cost';
        app(PostInventoryValuation::class)->outbound($c['f']['store'], $c['f']['first'], InventoryCostEntryKind::SaleIssue, '1.000000', 'sale', $c['sale']['id'], $reference, now()->toAtomString(), fn (?string $cost, InventoryCostState $state) => app(PostStockMovement::class)->handle(new StockMovementCommand(location: $c['location'], variant: $c['f']['first'], kind: InventoryMovementKind::Sale, quantityBase: '-1.000000', sourceType: 'sale', sourceReference: $c['sale']['id'], sourceLineReference: $reference, unitCost: $cost, costState: $state))['movement']);
        $this->assertNull($projection->readWindow($c['f']['merchant']));
        $row = $this->getJson($c['reportBase'].'/reports/bookkeeping'.$c['range'])->assertOk()->json('data.rows');
        $original = array_values(array_filter($row, fn ($r) => $r['document_number'] === $c['sale']['number']));
        $this->assertSame('120.00', $original[0]['cogs']);
    }

    public function test_return_actor_and_local_hours_are_frozen_across_dst_and_overnight_sessions(): void
    {
        foreach ([['2026-03-08T06:30:00Z', '2026-03-08T07:30:00Z', ['01:00', '03:00']], ['2026-03-09T03:50:00Z', '2026-03-09T04:30:00Z', ['00:00', '23:00']]] as [$saleAt, $returnAt, $hours]) {
            $this->travelTo(CarbonImmutable::parse($saleAt));
            $c = $this->soldFixture(timezone: 'America/New_York');
            $name = $c['f']['manager']->name;
            $this->travelTo(CarbonImmutable::parse($returnAt));
            $response = $this->actingAs($c['f']['manager'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales/'.$c['sale']['id'].'/returns', ['register_session_id' => $c['session']['id'], 'reason' => 'Return after clock change', 'lines' => [['sale_line_id' => $c['sale']['lines'][0]['id'], 'quantity' => '0.500000', 'disposition' => 'restock']], 'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'external_card', 'state' => 'pending_external_action', 'amount' => '59.00']]]);
            $this->assertSame(201, $response->status(), $response->getContent());
            $c['f']['manager']->update(['name' => 'Renamed after return']);
            $rows = $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/employees'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '150.00')->json('data.rows');
            $refund = array_values(array_filter($rows, fn ($row) => $row['revenue'] === '-50.00'));
            $this->assertSame($name, $refund[0]['label']);
            $actual = $this->getJson($c['reportBase'].'/reports/time-intervals'.$c['range'])->assertOk()->json('data.rows');
            $this->assertSame($hours, array_column($actual, 'label'));
            app(ReportProjection::class)->rebuild($c['f']['merchant']);
            $actual = $this->getJson($c['reportBase'].'/reports/time-intervals'.$c['range'])->assertOk()->json('data.rows');
            $this->assertSame($hours, array_column($actual, 'label'));
        }
    }

    public function test_weighted_bundle_and_multiple_extras_keep_revenue_and_component_costs_separate(): void
    {
        $c = $this->soldFixture();
        $c['f']['first']->product()->update(['type' => 'bundle']);
        BundleComponent::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'bundle_variant_id' => $c['f']['first']->id, 'component_variant_id' => $c['f']['second']->id, 'quantity_base' => '2.000000']);
        app(PostInventoryValuation::class)->inbound($c['f']['store'], $c['f']['second'], InventoryCostEntryKind::AdjustmentIn, '100.000000', '10.000000', 'opening', 'bundle-opening', 'main', now()->toAtomString(), fn (string $cost, InventoryCostState $state) => app(PostStockMovement::class)->handle(new StockMovementCommand(location: $c['location'], variant: $c['f']['second'], kind: InventoryMovementKind::OpeningBalance, quantityBase: '100.000000', sourceType: 'opening', sourceReference: 'bundle-opening', unitCost: $cost, costState: $state))['movement']);
        $group = ExtraGroup::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'product_id' => $c['f']['first']->product_id, 'name' => 'Bundle extras', 'minimum_selected' => 0, 'maximum_selected' => 2]);
        $paid = ExtraOption::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'extra_group_id' => $group->id, 'name' => 'Paid extra', 'is_active' => true, 'price_delta' => '20.00', 'stock_variant_id' => $c['f']['second']->id, 'stock_quantity_base' => '0.500000']);
        $free = ExtraOption::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'extra_group_id' => $group->id, 'name' => 'Free extra', 'is_active' => true, 'price_delta' => '0.00']);
        $cart = $this->actingAs($c['f']['cashier'])->postJson($c['base'].'/carts', ['inventory_location_id' => $c['location']->public_id])->assertCreated()->json('data');
        $url = $c['base'].'/carts/'.$cart['id'];
        $this->putJson($url, ['expected_version' => 1, 'lines' => [['variant_id' => $c['f']['first']->public_id, 'quantity' => '1.500000', 'extra_option_ids' => [$paid->public_id, $free->public_id]]]])->assertOk();
        $quote = $this->postJson($url.'/quotes', ['expected_version' => 2])->assertCreated()->assertJsonPath('quote.result.totals.gross', '212.40')->json('quote');
        $sale = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $c['session']['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '250.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '37.60'])->assertCreated()->json('data');
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-summary'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '380.00')->assertJsonPath('data.summary.cogs', '117.50');
        $this->getJson($c['reportBase'].'/reports/multiple-choice-products'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '30.00')->assertJsonPath('data.pagination.total', 2);
        $this->actingAs($c['f']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales/'.$sale['id'].'/returns', ['register_session_id' => $c['session']['id'], 'reason' => 'Partial bundle return', 'lines' => [['sale_line_id' => $sale['lines'][0]['id'], 'quantity' => '0.500000', 'disposition' => 'restock']], 'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '70.80', 'confirmation_source' => 'not_applicable']]])->assertCreated();
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-summary'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '320.00')->assertJsonPath('data.summary.cogs', '105.00');
        $this->getJson($c['reportBase'].'/reports/multiple-choice-products'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '20.00');
    }

    public function test_rebuildable_projection_matches_ledgers_and_never_serves_a_stale_refund(): void
    {
        $c = $this->soldFixture();
        $this->actingAs($c['f']['owner']);
        $before = [];
        foreach (ReportCatalog::all() as $key => $report) {
            if (in_array($report['type'], ['sales', 'transactions', 'customers', 'profit'], true)) {
                $before[$key] = $this->getJson($c['reportBase'].'/reports/'.$key.$c['range'])->assertOk()->json('data');
            }
        }
        $projection = app(ReportProjection::class);
        $projection->rebuild($c['f']['merchant']);
        $this->assertTrue($projection->isCurrent($c['f']['merchant']));
        foreach ($before as $key => $expected) {
            $actual = $this->getJson($c['reportBase'].'/reports/'.$key.$c['range'])->assertOk()->json('data');
            $this->assertSame($expected['summary'], $actual['summary'], $key);
            $this->assertEquals($expected['rows'], $actual['rows'], $key);
        }
        $this->actingAs($c['f']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales/'.$c['sale']['id'].'/returns', [
            'register_session_id' => $c['session']['id'], 'reason' => 'Projection freshness',
            'lines' => [['sale_line_id' => $c['sale']['lines'][0]['id'], 'quantity' => '0.500000', 'disposition' => 'restock']],
            'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '59.00', 'confirmation_source' => 'not_applicable']],
        ])->assertCreated();
        $this->assertFalse($projection->isCurrent($c['f']['merchant']));
        $this->assertNotNull($projection->readWindow($c['f']['merchant']));
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '150.00');
        $live = [];
        foreach (['sales-summary', 'sales-by-product', 'tax-report', 'daily-sales', 'monthly-tax', 'quarterly-tax'] as $key) {
            $live[$key] = $this->getJson($c['reportBase'].'/reports/'.$key.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '150.00')->assertJsonPath('data.summary.cogs', '60.00')->json('data');
        }
        $projection->refresh($c['f']['merchant']);
        $projection->refresh($c['f']['merchant']);
        $this->assertTrue($projection->isCurrent($c['f']['merchant']));
        foreach ($live as $key => $expected) {
            $actual = $this->getJson($c['reportBase'].'/reports/'.$key.$c['range'])->assertOk()->json('data');
            $this->assertSame($expected['summary'], $actual['summary'], $key);
            $this->assertEquals($expected['rows'], $actual['rows'], $key);
        }
        $this->postReportingSale($c, [['variant_id' => $c['f']['first']->public_id, 'quantity' => '1.000000']]);
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '250.00');
        $projection->refresh($c['f']['merchant']);
        $this->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '250.00')->assertJsonPath('data.summary.cogs', '100.00');
        $this->actingAs($c['f']['cashier'])->getJson($c['reportBase'].'/reports/sales-by-product'.$c['range'])->assertOk()->assertJsonMissingPath('data.rows.0.cogs');
        DB::table('sale_lines')->where('merchant_id', $c['f']['merchant']->id)->update(['reporting_snapshot' => null]);
        $this->artisan('reports:backfill')->assertSuccessful();
        $this->assertFalse($projection->isCurrent($c['f']['merchant']));
    }

    public function test_pending_refunds_reduce_revenue_without_reversing_discarded_stock_costs(): void
    {
        $c = $this->soldFixture();
        $return = $this->actingAs($c['f']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales/'.$c['sale']['id'].'/returns', [
            'register_session_id' => $c['session']['id'], 'reason' => 'Damaged item',
            'lines' => [['sale_line_id' => $c['sale']['lines'][0]['id'], 'quantity' => '1.000000', 'disposition' => 'discard']],
            'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'external_card', 'state' => 'pending_external_action', 'amount' => '118.00']],
        ])->assertCreated()->assertJsonPath('data.state', 'refund_pending')->json('data');
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-summary'.$c['range'])->assertOk()
            ->assertJsonPath('data.summary.revenue', '100.00')->assertJsonPath('data.summary.tax', '18.00')->assertJsonPath('data.summary.cogs', '80.00')->assertJsonPath('data.summary.gross_profit', '20.00');
        $this->getJson($c['reportBase'].'/reports/tender-breakdown'.$c['range'])->assertOk()->assertJsonPath('data.summary.amount', '236.00')->assertJsonPath('data.summary.pending_refunds', '118.00');
        $this->actingAs($c['f']['cashier'])->postJson($c['base'].'/sale-returns/'.$return['id'].'/refund-tenders/'.$return['refund_tenders'][0]['id'].'/confirm', ['external_reference' => 'REPORT-REFUND', 'confirmation_source' => 'staff_declaration'])->assertOk();
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/tender-breakdown'.$c['range'])->assertOk()->assertJsonPath('data.summary.amount', '118.00')->assertJsonPath('data.summary.pending_refunds', '0.00');
        $this->getJson($c['reportBase'].'/reports/sales-summary'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '100.00')->assertJsonPath('data.summary.cogs', '80.00');
    }

    public function test_stock_history_keeps_same_named_locations_separate(): void
    {
        $c = $this->soldFixture();
        $location = InventoryLocation::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'store_id' => $c['f']['store']->id, 'code' => 'SECOND', 'name' => 'Main']);
        app(PostStockMovement::class)->handle(new StockMovementCommand(location: $location, variant: $c['f']['first'], kind: InventoryMovementKind::OpeningBalance, quantityBase: '5.000000', sourceType: 'opening', sourceReference: 'second-location', actor: $c['f']['owner']));
        $rows = $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/stock-history'.$c['range'])->assertOk()->assertJsonPath('data.pagination.total', 2)->json('data.rows');
        $this->assertCount(2, array_unique(array_column($rows, 'group_key')));
        $quantities = array_map('floatval', array_column($rows, 'closing_quantity'));
        sort($quantities);
        $this->assertSame([5.0, 98.0], $quantities);
        DB::table('inventory_reorder_policies')->insert(['merchant_id' => $c['f']['merchant']->id, 'store_id' => $c['f']['store']->id, 'product_variant_id' => $c['f']['first']->id, 'minimum_quantity' => '100.000000', 'target_quantity' => '150.000000', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson($c['reportBase'].'/reports/stock-warnings'.$c['range'])->assertOk()->assertJsonPath('data.pagination.total', 0);
        $this->getJson($c['reportBase'].'/reports/stock-history'.$c['range'].'&inventory_location_id='.$location->public_id)->assertOk()->assertJsonPath('data.pagination.total', 1);
    }

    public function test_discount_allocation_refunds_reconcile_to_the_cent_and_rebuild_idempotently(): void
    {
        $c = $this->soldFixture(['line' => '0.01', 'cart' => '0.01']);
        $this->assertSame(2, DB::table('sale_discount_allocations')->count());
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($c['f']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales/'.$c['sale']['id'].'/returns', [
                'register_session_id' => $c['session']['id'], 'reason' => 'Rounding acceptance', 'lines' => [['sale_line_id' => $c['sale']['lines'][0]['id'], 'quantity' => '1.000000', 'disposition' => 'restock']],
                'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '117.99', 'confirmation_source' => 'not_applicable']],
            ])->assertCreated();
        }
        $this->assertSame('0.00', Decimal::fixed(Decimal::of((string) DB::table('sale_discount_allocations')->sum('amount')), 2));
        $before = DB::table('sale_discount_allocations')->count();
        $this->artisan('reports:backfill')->assertSuccessful();
        $this->artisan('reports:backfill')->assertSuccessful();
        $this->assertSame($before, DB::table('sale_discount_allocations')->count());
    }

    public function test_closed_session_snapshots_are_immutable_and_reopening_adds_a_revision(): void
    {
        $c = $this->soldFixture();
        $session = CashSession::query()->where('merchant_id', $c['f']['merchant']->id)->firstOrFail();
        app(SubmitCashSessionCount::class)->handle($session, $c['f']['cashier'], (string) Str::uuid(), $session->version, '1236.00', null, 0, 0);
        $first = DB::table('cash_closeout_snapshots')->first();
        $this->assertNotNull($first);
        $session->refresh()->save();
        $this->assertSame(1, DB::table('cash_closeout_snapshots')->count());
        $session->forceFill(['status' => CashSessionStatus::VarianceReview])->save();
        app(ReviewCashSession::class)->handle($session, $c['f']['owner'], (string) Str::uuid(), $session->version, 'reopen', 'Acceptance recount');
        $session->refresh();
        app(SubmitCashSessionCount::class)->handle($session, $c['f']['cashier'], (string) Str::uuid(), $session->version, '1236.00', null, 0, 0);
        $this->assertSame(2, DB::table('cash_closeout_snapshots')->count());
        $this->assertSame($first->content_hash, DB::table('cash_closeout_snapshots')->where('id', $first->id)->value('content_hash'));
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/z-report'.$c['range'])->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.summary.revenue', '200.00')->assertJsonPath('data.summary.expected_amount', '1236.00');
        $this->getJson($c['reportBase'].'/reports/eod-variance-history'.$c['range'])->assertOk()->assertJsonPath('data.pagination.total', 2);
    }

    public function test_metadata_import_validates_before_commit_and_roundtrips_without_changing_history(): void
    {
        Storage::fake('local');
        $c = $this->soldFixture();
        $csv = implode(',', ReportSetupImport::HEADERS)."\nTEA-1,,,BRAND,,,Large,yes,3,10\n";
        $url = '/api/v1/merchants/'.$c['f']['merchant']->public_id.'/stores/'.$c['f']['store']->public_id.'/imports';
        $this->actingAs($c['f']['owner']);
        $job = $this->post($url, ['resource' => 'reporting_catalog', 'file' => UploadedFile::fake()->createWithContent('metadata.csv', $csv)])->assertCreated()->assertJsonPath('data.state', 'validated')->json('data');
        $this->assertDatabaseCount('inventory_reorder_policies', 0);
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->postJson($url.'/'.$job['public_id'].'/commit')->assertOk();
        $this->withHeader('Idempotency-Key', $key)->postJson($url.'/'.$job['public_id'].'/commit')->assertOk();
        $this->assertDatabaseCount('inventory_reorder_policies', 1);
        $this->assertSame('Large', $c['f']['first']->refresh()->attributes['size']);
        $bytes = $this->get($c['reportBase'].'/setup/export.csv?store_id='.$c['f']['store']->public_id)->assertOk()->streamedContent();
        $this->assertStringContainsString('brand_code', $bytes);
        $this->assertStringContainsString('Large,yes,3', $bytes);
        $this->getJson($c['reportBase'].'/reports/size'.$c['range'])->assertOk()->assertJsonPath('data.rows.0.label', 'Not captured');
        $brand = DB::table('product_brands')->where('merchant_id', $c['f']['merchant']->id)->first();
        DB::table('product_brands')->where('id', $brand->id)->update(['archived_at' => now()]);
        $this->getJson($c['reportBase'].'/catalog')->assertOk()->assertJsonPath('data.lookups.product_brands.0.archived', true);
        $row = array_replace(array_fill_keys(ReportSetupImport::HEADERS, ''), ['sku' => 'TEA-1', 'brand_code' => 'BRAND', 'miscellaneous' => 'yes']);
        $resolved = app(ReportSetupImport::class)->resolve($c['f']['merchant'], $c['f']['store'], $row);
        $this->assertSame($brand->id, $resolved['product']['product_brand_id']);
        $otherProduct = Product::factory()->create(['merchant_id' => $c['f']['merchant']->id, 'store_id' => null]);
        $classification = ['product_brand_id' => Uuid::fromBytes($brand->public_id)->toString(), 'store_id' => $c['f']['store']->public_id];
        $this->putJson($c['reportBase'].'/products/'.$otherProduct->public_id, $classification)->assertUnprocessable();
        $this->putJson($c['reportBase'].'/products/'.$c['f']['first']->product->public_id, $classification)->assertOk();
    }

    public function test_permission_revocation_blocks_queued_exports_and_both_download_routes(): void
    {
        Storage::fake('local');
        $c = $this->soldFixture();
        $this->actingAs($c['f']['owner']);
        $this->postJson($c['reportBase'].'/reports/gross-profit/exports'.$c['range'], ['format' => 'csv'])->assertAccepted();
        $ready = DataTransferJob::query()->latest('id')->firstOrFail();
        Queue::fake([ProcessReportExport::class]);
        $this->postJson($c['reportBase'].'/reports/gross-profit/exports'.$c['range'], ['format' => 'csv'])->assertAccepted();
        $queued = DataTransferJob::query()->latest('id')->firstOrFail();
        MerchantMembership::query()->where('merchant_id', $c['f']['merchant']->id)->where('user_id', $c['f']['owner']->id)->update(['role' => 'manager']);
        $this->get($c['reportBase'].'/exports/'.$ready->public_id.'/download')->assertForbidden();
        $this->get('/api/v1/merchants/'.$c['f']['merchant']->public_id.'/data-transfers/'.$ready->public_id.'/download')->assertForbidden();
        try {
            app(ReportExporter::class)->execute($queued);
            $this->fail('A revoked financial permission must prevent export processing.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('failed', $queued->refresh()->state);
    }

    public function test_independent_catalogues_keep_matching_skus_and_classification_codes_in_their_store(): void
    {
        $f = $this->pricingFixture(true);
        $otherProduct = Product::factory()->create(['merchant_id' => $f['merchant']->id, 'store_id' => $f['otherStore']->id]);
        $otherVariant = ProductVariant::factory()->create(['merchant_id' => $f['merchant']->id, 'store_id' => $f['otherStore']->id, 'product_id' => $otherProduct->id, 'base_unit_id' => $f['first']->base_unit_id, 'sku' => 'TEA-1']);
        $base = '/api/v1/merchants/'.$f['merchant']->public_id.'/reporting';
        $this->actingAs($f['owner']);
        foreach ([$f['store'], $f['otherStore']] as $store) {
            $this->postJson($base.'/setup/brands', ['store_id' => $store->public_id, 'name' => $store->name.' brand', 'code' => 'LOCAL'])->assertCreated();
        }
        $row = array_replace(array_fill_keys(ReportSetupImport::HEADERS, ''), ['sku' => 'TEA-1', 'brand_code' => 'LOCAL', 'miscellaneous' => 'no']);
        $first = app(ReportSetupImport::class)->resolve($f['merchant'], $f['store'], $row);
        $second = app(ReportSetupImport::class)->resolve($f['merchant'], $f['otherStore'], $row);
        $this->assertSame($f['first']->id, $first['variant']->id);
        $this->assertSame($otherVariant->id, $second['variant']->id);
        $this->assertNotSame($first['product']['product_brand_id'], $second['product']['product_brand_id']);
        $this->putJson($base.'/products/'.$otherProduct->public_id, ['store_id' => $f['store']->public_id])->assertNotFound();
        $membership = MerchantMembership::query()->where('merchant_id', $f['merchant']->id)->where('user_id', $f['manager']->id)->firstOrFail();
        DB::table('store_assignments')->where('merchant_membership_id', $membership->id)->where('store_id', $f['otherStore']->id)->delete();
        $this->actingAs($f['manager'])->getJson($base.'/setup?store_id='.$f['otherStore']->public_id)->assertNotFound();
        $this->getJson($base.'/setup?store_id='.$f['store']->public_id)->assertOk()->assertJsonCount(1, 'data.products.data');
    }

    public function test_every_catalogue_report_runs_on_an_empty_ledger(): void
    {
        $f = $this->pricingFixture();
        $this->actingAs($f['owner']);
        $base = '/api/v1/merchants/'.$f['merchant']->public_id.'/reporting';
        $this->getJson($base.'/catalog')->assertOk();
        foreach (ReportCatalog::all() as $key => $report) {
            $this->getJson($base.'/reports/'.$key.'?start=2026-09-01&end=2026-09-30')->assertOk()->assertJsonPath('data.report', $key);
        }
    }

    public function test_unavailable_costs_never_become_zero_profit(): void
    {
        $c = $this->soldFixture();
        DB::table('inventory_cost_entries')->where('source_type', 'sale')->update(['value_delta' => null, 'cost_state' => 'pending']);
        $this->actingAs($c['f']['owner'])->getJson($c['reportBase'].'/reports/sales-summary'.$c['range'])
            ->assertOk()->assertJsonPath('data.summary.revenue', '200.00')->assertJsonPath('data.summary.cogs', null)->assertJsonPath('data.summary.gross_profit', null);
        $this->getJson($c['reportBase'].'/reports/gross-profit'.$c['range'])->assertOk()->assertJsonPath('data.rows.0.gross_profit', null);
    }

    public function test_sales_returns_tax_costs_and_snapshot_classifications_reconcile(): void
    {
        $c = $this->soldFixture();
        $this->actingAs($c['f']['owner']);
        $base = $c['reportBase'];
        $this->getJson($base.'/reports/sales-summary'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '200.00')->assertJsonPath('data.summary.tax', '36.00')->assertJsonPath('data.summary.cogs', '80.00')->assertJsonPath('data.summary.gross_profit', '120.00');
        $this->actingAs($c['f']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales/'.$c['sale']['id'].'/returns', [
            'register_session_id' => $c['session']['id'], 'reason' => 'Partial return', 'lines' => [['sale_line_id' => $c['sale']['lines'][0]['id'], 'quantity' => '0.500000', 'disposition' => 'restock']],
            'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '59.00', 'confirmation_source' => 'not_applicable']],
        ])->assertCreated();
        $this->actingAs($c['f']['owner'])->getJson($base.'/reports/sales-summary'.$c['range'])->assertOk()->assertJsonPath('data.summary.revenue', '150.00')->assertJsonPath('data.summary.tax', '27.00')->assertJsonPath('data.summary.sales_including_tax', '177.00')->assertJsonPath('data.summary.cogs', '60.00')->assertJsonPath('data.summary.gross_profit', '90.00');
        $this->getJson($base.'/reports/tender-breakdown'.$c['range'])->assertOk()->assertJsonPath('data.summary.amount', '177.00');
        $this->getJson($base.'/reports/bookkeeping'.$c['range'])->assertOk()->assertJsonPath('data.pagination.total', 2)->assertJsonPath('data.summary.revenue', '150.00');
        DB::table('product_brands')->where('merchant_id', $c['f']['merchant']->id)->update(['name' => 'Renamed brand']);
        $this->getJson($base.'/reports/brands'.$c['range'])->assertOk()->assertJsonPath('data.rows.0.label', 'Original brand');
        foreach (ReportCatalog::all() as $key => $report) {
            $this->getJson($base.'/reports/'.$key.$c['range'])->assertOk();
        }
    }

    public function test_roles_foreign_stores_and_sensitive_export_downloads_are_enforced(): void
    {
        $c = $this->soldFixture();
        $this->actingAs($c['f']['cashier'])->getJson($c['reportBase'].'/reports/sales-summary'.$c['range'])->assertOk()->assertJsonMissingPath('data.summary.cogs')->assertJsonMissingPath('data.rows.0.gross_profit');
        $this->getJson($c['reportBase'].'/reports/gross-profit'.$c['range'])->assertForbidden();
        $this->postJson($c['reportBase'].'/reports/sales-summary/exports'.$c['range'], ['format' => 'csv'])->assertForbidden();
        $this->actingAs($c['f']['manager'])->getJson($c['reportBase'].'/reports/bookkeeping'.$c['range'])->assertForbidden();
        $foreign = Store::factory()->for(Merchant::factory())->create();
        $this->getJson($c['reportBase'].'/reports/sales-summary?store_id='.$foreign->public_id)->assertNotFound();
        $this->getJson($c['reportBase'].'/reports/sales-summary'.$c['range'].'&sort=amount%20DESC;DROP')->assertUnprocessable();
    }

    public function test_complete_csv_excel_and_pdf_exports_use_the_same_rows(): void
    {
        Storage::fake('local');
        $c = $this->soldFixture();
        $this->actingAs($c['f']['owner']);
        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->postJson($c['reportBase'].'/reports/sales-summary/exports'.$c['range'], ['format' => $format])->assertAccepted();
            $job = DataTransferJob::query()->latest('id')->firstOrFail();
            $this->assertSame('ready', $job->state);
            $this->assertSame(1, $job->total_rows);
            $bytes = Storage::disk('local')->get($job->artifact_path);
            $this->assertStringStartsWith(match ($format) {
                'xlsx' => 'PK', 'pdf' => '%PDF', default => "\xEF\xBB\xBF"
            }, $bytes);
            $date = substr($c['session']['business_date'], 0, 10);
            $this->get($c['reportBase'].'/exports/'.$job->public_id.'/download')->assertOk()->assertDownload('sales-summary-'.$date.'-to-'.$date.'.'.$format);
        }
        $job->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->getJson($c['reportBase'].'/exports')->assertOk()->assertJsonPath('data.0.state', 'expired')->assertJsonPath('data.0.download_available', false);
        $this->get($c['reportBase'].'/exports/'.$job->public_id.'/download')->assertGone();
    }

    public function test_cart_replacement_only_records_real_quantity_reductions(): void
    {
        $c = $this->soldFixture();
        $cart = $this->actingAs($c['f']['cashier'])->postJson($c['base'].'/carts', ['inventory_location_id' => $c['location']->public_id])->assertCreated()->json('data');
        $url = $c['base'].'/carts/'.$cart['id'];
        $payload = ['expected_version' => 1, 'lines' => [['variant_id' => $c['f']['first']->public_id, 'quantity' => '2.000000']]];
        $this->putJson($url, $payload)->assertOk();
        $payload['expected_version'] = 2;
        $this->putJson($url, $payload)->assertOk();
        $this->assertSame(0, DB::table('cart_audit_events')->count());
        $payload['expected_version'] = 3;
        $payload['lines'][0]['quantity'] = '1.000000';
        $payload['change_reason'] = 'Customer changed quantity';
        $this->putJson($url, $payload)->assertOk();
        $this->assertDatabaseHas('cart_audit_events', ['event' => 'quantity_reduced', 'reason' => 'Customer changed quantity']);
        $this->assertSame(1, DB::table('cart_audit_events')->count());
    }

    public function test_metadata_csv_escaping_roundtrips_formula_text_and_literal_quotes(): void
    {
        Storage::fake('local');
        $f = $this->pricingFixture();
        $f['first']->forceFill(['attributes' => ['size' => '=Large']])->save();
        $f['second']->forceFill(['attributes' => ['size' => "'=Small"]])->save();
        $this->actingAs($f['owner']);
        $base = '/api/v1/merchants/'.$f['merchant']->public_id;
        $csv = $this->get($base.'/reporting/setup/export.csv?store_id='.$f['store']->public_id)->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Large", $csv);
        $this->assertStringContainsString("''=Small", $csv);
        $imports = $base.'/stores/'.$f['store']->public_id.'/imports';
        $job = $this->post($imports, ['resource' => 'reporting_catalog', 'file' => UploadedFile::fake()->createWithContent('metadata.csv', $csv)])->assertCreated()->assertJsonPath('data.state', 'validated')->json('data');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($imports.'/'.$job['public_id'].'/commit')->assertOk();
        $this->assertSame('=Large', $f['first']->refresh()->attributes['size']);
        $this->assertSame("'=Small", $f['second']->refresh()->attributes['size']);
    }

    public function test_spreadsheet_exports_keep_negative_numbers_numeric_and_formula_labels_as_text(): void
    {
        Storage::fake('local');
        $c = $this->soldFixture();
        DB::table('sale_lines')->where('merchant_id', $c['f']['merchant']->id)->update(['product_name_snapshot' => '=1+1']);
        $this->actingAs($c['f']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($c['base'].'/sales/'.$c['sale']['id'].'/returns', [
            'register_session_id' => $c['session']['id'], 'reason' => 'Export reconciliation', 'lines' => [['sale_line_id' => $c['sale']['lines'][0]['id'], 'quantity' => '0.500000', 'disposition' => 'restock']],
            'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '59.00', 'confirmation_source' => 'not_applicable']],
        ])->assertCreated();
        $this->actingAs($c['f']['owner']);
        $this->postJson($c['reportBase'].'/reports/bookkeeping/exports'.$c['range'], ['format' => 'csv'])->assertAccepted();
        $job = DataTransferJob::query()->latest('id')->firstOrFail();
        $csv = Storage::disk('local')->get($job->artifact_path);
        $records = array_map(fn ($line) => str_getcsv($line, escape: ''), preg_split('/\r?\n/', trim($csv)));
        $header = array_shift($records);
        $revenues = array_column($records, array_search('Revenue', $header, true));
        sort($revenues);
        $this->assertSame(['-50.00', '200.00'], $revenues);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->postJson($c['reportBase'].'/reports/bookkeeping/exports'.$c['range'], ['format' => 'xlsx'])->assertAccepted();
        $job = DataTransferJob::query()->latest('id')->firstOrFail();
        $reader = new Reader;
        $path = Storage::disk('local')->path($job->artifact_path);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheetXml = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();
        $this->assertIsString($sheetXml);
        $this->assertDoesNotMatchRegularExpression('/<f(?:\s|>)/', $sheetXml);
        $reader->open($path);
        $count = 0;
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Detail 1') {
                    continue;
                }
                $headers = [];
                foreach ($sheet->getRowIterator() as $row) {
                    if ($headers === []) {
                        $headers = $row->toArray();

                        continue;
                    }
                    $this->assertSame('=1+1', $row->getCellAtIndex(array_search('Product', $headers, true))->getValue());
                    $revenue = $row->getCellAtIndex(array_search('Revenue', $headers, true))->getValue();
                    $this->assertTrue(is_int($revenue) || is_float($revenue));
                    $this->assertEqualsWithDelta(0.6, $row->getCellAtIndex(array_search('Margin %', $headers, true))->getValue(), 0.000001);
                    $count++;
                }
            }
        } finally {
            $reader->close();
        }
        $this->assertSame(2, $count);
    }
}
