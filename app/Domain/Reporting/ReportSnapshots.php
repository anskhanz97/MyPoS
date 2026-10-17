<?php

namespace App\Domain\Reporting;

use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Pricing\DiscountAllocator;
use App\Domain\Shared\Math\Decimal;
use App\Models\Cart;
use App\Models\CartQuote;
use App\Models\CashSession;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class ReportSnapshots
{
    public const DIMENSIONS = ['product_category_id' => 'product_categories', 'reporting_category_id' => 'reporting_categories', 'product_brand_id' => 'product_brands', 'product_reporting_type_id' => 'product_reporting_types', 'primary_supplier_id' => 'suppliers'];

    public function register(): void
    {
        SaleReturn::creating(function (SaleReturn $return): void {
            $store = DB::table('stores')->where('id', $return->store_id)->first();
            $register = DB::table('registers')->where('id', $return->register_id)->first();
            $group = $register?->register_group_id === null ? null : DB::table('register_groups')->where('id', $register->register_group_id)->first();
            $actor = DB::table('users')->where('id', $return->created_by_user_id)->first();
            $sale = Sale::query()->findOrFail($return->sale_id);
            $original = json_decode($sale->reporting_snapshot ?? '{}', true, flags: JSON_THROW_ON_ERROR);
            $return->mergeCasts(['reporting_snapshot' => 'array']);
            $return->forceFill(['reporting_snapshot' => ['version' => 1, 'provenance' => 'captured', 'store' => $store?->name,
                'employee' => $actor?->name, 'register' => $register?->name, 'register_group' => ($group !== null ? $group->name : 'Ungrouped'), 'register_group_id' => $group?->id,
                'customer_group' => $original['customer_group'] ?? 'Not captured', 'timezone' => $store?->timezone,
                'utc_offset_minutes' => CarbonImmutable::parse($return->posted_at)->setTimezone(($store !== null ? $store->timezone : 'UTC'))->offsetMinutes]]);
        });
        Sale::creating(function (Sale $sale): void {
            $store = DB::table('stores')->where('id', $sale->store_id)->first();
            $register = DB::table('registers')->where('id', $sale->register_id)->first();
            $customer = $sale->customer_id === null ? null : DB::table('operational_customers')->where('id', $sale->customer_id)->first();
            $group = $customer?->customer_group_id === null ? null : DB::table('customer_groups')->where('id', $customer->customer_group_id)->first();
            $registerGroup = $register?->register_group_id === null ? null : DB::table('register_groups')->where('id', $register->register_group_id)->first();
            $actor = DB::table('users')->where('id', $sale->created_by_user_id)->first();
            $sale->mergeCasts(['reporting_snapshot' => 'array']);
            $sale->forceFill(['reporting_timezone' => ($store !== null ? $store->timezone : 'Asia/Karachi'), 'register_group_id' => $register?->register_group_id,
                'customer_group_id' => $customer?->customer_group_id, 'reporting_snapshot' => ['version' => 1, 'provenance' => 'captured',
                    'customer_group' => ($group !== null ? $group->name : 'Walk-in / unassigned'), 'register_group' => ($registerGroup !== null ? $registerGroup->name : 'Ungrouped'),
                    'store' => $store?->name, 'register' => $register?->name, 'employee' => $actor?->name,
                    'utc_offset_minutes' => CarbonImmutable::parse($sale->finalized_at)->setTimezone($store !== null ? $store->timezone : 'Asia/Karachi')->offsetMinutes]]);
        });
        SaleLine::creating(function (SaleLine $line): void {
            $variant = ProductVariant::query()->with('product')->findOrFail($line->product_variant_id);
            $snapshot = ['version' => 1, 'provenance' => 'captured'];
            foreach (self::DIMENSIONS as $column => $table) {
                $id = $variant->product->getAttribute($column);
                $line->setAttribute($column, $id);
                $row = $id === null ? null : DB::table($table)->where('id', $id)->first();
                $snapshot[$column] = ($row !== null ? $row->name : 'Unclassified');
                if ($column === 'product_reporting_type_id') {
                    $snapshot['wet_dry'] = ($row !== null ? ($row->classification ?? 'Unclassified') : 'Unclassified');
                }
            }
            $sale = Sale::query()->findOrFail($line->sale_id);
            $quote = CartQuote::query()->find($sale->cart_quote_id);
            $quoted = $quote?->payload['lines'][$line->sequence - 1] ?? [];
            $line->mergeCasts(['reporting_snapshot' => 'array']);
            $line->forceFill(['reporting_size' => $variant->attributes['size'] ?? null, 'is_miscellaneous' => (bool) $variant->product->getAttribute('is_miscellaneous'),
                'tax_rate_snapshot' => $quoted['tax_rate_percent'] ?? null, 'tax_classification_snapshot' => $variant->product->tax_classification_reference,
                'reporting_snapshot' => $snapshot]);
        });
        SaleLine::created(fn (SaleLine $line) => $this->captureDiscounts($line));
        SaleReturnLine::created(fn (SaleReturnLine $line) => $this->captureReturnDiscounts($line));
        StockMovement::creating(function (StockMovement $movement): void {
            $reference = substr((string) $movement->source_line_reference, 0, 36);
            if (! Uuid::isValid($reference)) {
                return;
            }
            if ($movement->source_type === 'sale') {
                $line = SaleLine::query()->where('merchant_id', $movement->merchant_id)->where('store_id', $movement->store_id)->where('public_id', Uuid::fromString($reference)->getBytes())->first();
                $movement->setAttribute('sale_line_id', $line?->id);
            } elseif ($movement->source_type === 'sale_return') {
                $line = SaleReturnLine::query()->where('merchant_id', $movement->merchant_id)->where('store_id', $movement->store_id)->where('public_id', Uuid::fromString($reference)->getBytes())->first();
                $movement->setAttribute('sale_return_line_id', $line?->id);
            }
        });
        CashSession::saved(function (CashSession $session): void {
            if ($session->isDirty('status') && $session->status === CashSessionStatus::Closed) {
                $this->closeout($session);
            }
        });
    }

    public function captureDiscounts(SaleLine $line): void
    {
        DB::transaction(function () use ($line): void {
            DB::table('sale_lines')->where('id', $line->id)->lockForUpdate()->first();
            if (DB::table('sale_discount_allocations')->where('sale_line_id', $line->id)->whereNull('sale_return_line_id')->exists()) {
                return;
            }
            $sale = Sale::query()->findOrFail($line->sale_id);
            $groups = [];
            foreach ($line->allocations ?? [] as $allocation) {
                $key = json_encode([$allocation['source'], $allocation['rule_id'] ?? null, $allocation['rule_name'] ?? null], JSON_THROW_ON_ERROR);
                $groups[$key] ??= ['source' => $allocation['source'], 'rule_id' => $allocation['rule_id'] ?? null, 'rule_name' => $allocation['rule_name'] ?? null, 'amount' => '0.00'];
                $groups[$key]['amount'] = Decimal::fixed(Decimal::of($groups[$key]['amount'])->plus($allocation['amount']), 2);
            }
            foreach ($groups as $allocation) {
                DB::table('sale_discount_allocations')->insert($allocation + ['merchant_id' => $sale->merchant_id, 'store_id' => $sale->store_id, 'sale_line_id' => $line->id, 'business_date' => $sale->business_date]);
            }
        });
    }

    public function captureReturnDiscounts(SaleReturnLine $line): void
    {
        DB::transaction(function () use ($line): void {
            $original = SaleLine::query()->findOrFail($line->sale_line_id);
            $this->captureDiscounts($original);
            if (DB::table('sale_discount_allocations')->where('sale_return_line_id', $line->id)->exists()) {
                return;
            }
            $return = DB::table('sale_returns')->where('id', $line->sale_return_id)->first();
            $allocations = DB::table('sale_discount_allocations')->where('sale_line_id', $original->id)->whereNull('sale_return_line_id')->orderBy('id')->get();
            $entitlement = (string) DB::table('sale_return_lines')->where('sale_line_id', $original->id)->where('id', '<=', $line->id)->sum('discount');
            $weights = array_values($allocations->map(fn ($a, $index) => ['line_id' => (string) $a->id, 'sequence' => $index, 'weight' => Decimal::of((string) $a->amount)])->all());
            $cumulative = app(DiscountAllocator::class)->allocate(Decimal::of($entitlement), $weights);
            foreach ($allocations as $allocation) {
                $prior = DB::table('sale_discount_allocations')->where('sale_line_id', $original->id)->whereNotNull('sale_return_line_id')->where('source', $allocation->source)->where('rule_id', $allocation->rule_id)->where('rule_name', $allocation->rule_name)->sum('amount');
                $amount = ($cumulative[(string) $allocation->id] ?? Decimal::of('0'))->plus((string) $prior)->negated();
                DB::table('sale_discount_allocations')->insert(['merchant_id' => $original->merchant_id, 'store_id' => $original->store_id, 'sale_line_id' => $original->id,
                    'sale_return_line_id' => $line->id, 'source' => $allocation->source, 'rule_id' => $allocation->rule_id, 'rule_name' => $allocation->rule_name,
                    'amount' => Decimal::fixed($amount, 2), 'business_date' => $return->business_date]);
            }
        });
    }

    public function closeout(CashSession $session): void
    {
        $payload = ['definition_version' => 2, 'session' => $session->public_id, 'number' => $session->number, 'business_date' => $session->business_date->toDateString(),
            'expected_cash' => $session->expected_amount, 'counted_cash' => $session->counted_amount, 'variance' => $session->variance_amount,
            'closed_by_user_id' => $session->closed_by_user_id, 'closed_at' => $session->closed_at?->toAtomString(),
            'cash_change' => (string) DB::table('sales')->where('cash_session_id', $session->id)->sum('cash_change'),
            'refund_tenders' => DB::table('sale_return_tenders as t')->join('sale_returns as r', 'r.id', '=', 't.sale_return_id')->where('r.cash_session_id', $session->id)->selectRaw('t.type, t.state, SUM(t.amount) amount')->groupBy('t.type', 't.state')->get()->all(),
            'sales' => (array) DB::table('sales')->where('cash_session_id', $session->id)->selectRaw('COUNT(*) transactions, SUM(net_total) revenue, SUM(tax_total) tax, SUM(total) sales_including_tax')->first(),
            'returns' => (array) DB::table('sale_returns')->where('cash_session_id', $session->id)->selectRaw('COUNT(*) transactions, SUM(net_total) revenue, SUM(tax_total) tax, SUM(total) returns_including_tax')->first(),
            'tenders' => DB::table('sale_tenders as t')->join('sales as s', 's.id', '=', 't.sale_id')->where('s.cash_session_id', $session->id)->selectRaw('t.type, SUM(t.amount) amount')->groupBy('t.type')->get()->all(),
            'reviews' => DB::table('cash_variance_reviews')->where('cash_session_id', $session->id)->orderBy('id')->get()->map(fn ($r) => ['decision' => $r->decision, 'reason' => $r->reason, 'reviewed_at' => $r->reviewed_at])->all()];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        DB::table('cash_closeout_snapshots')->insertOrIgnore(['public_id' => Str::uuid7()->getBytes(), 'merchant_id' => $session->merchant_id, 'store_id' => $session->store_id,
            'cash_session_id' => $session->id, 'revision' => $session->version, 'movement_high_watermark' => DB::table('cash_movements')->where('cash_session_id', $session->id)->max('id') ?? 0,
            'payload' => $json, 'content_hash' => hash('sha256', $json), 'occurred_at' => now()]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function cartQuantities(Cart $cart): array
    {
        $result = [];
        foreach ($cart->lines()->with('extras')->get() as $line) {
            $options = $line->extras->pluck('extra_option_id')->sort()->values()->all();
            $key = json_encode([$line->product_variant_id, $line->unit_id, $options], JSON_THROW_ON_ERROR);
            $result[$key] ??= ['quantity' => '0.000000', 'subject' => $line->product_name_snapshot, 'sku' => $line->sku_snapshot, 'unit' => $line->unit_symbol_snapshot];
            $result[$key]['quantity'] = Decimal::fixed(Decimal::of($result[$key]['quantity'])->plus($line->quantity_uom), 6);
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $before
     */
    public function recordCartChanges(Cart $cart, User $actor, array $before, ?string $reason): void
    {
        $after = $this->cartQuantities($cart);
        foreach ($before as $key => $row) {
            $delta = Decimal::of($after[$key]['quantity'] ?? '0')->minus($row['quantity']);
            if (! $delta->isNegative()) {
                continue;
            }
            DB::table('cart_audit_events')->insert(['public_id' => Str::uuid7()->getBytes(), 'merchant_id' => $cart->merchant_id, 'store_id' => $cart->store_id,
                'register_id' => $cart->register_id, 'cart_id' => $cart->id, 'actor_user_id' => $actor->id, 'cart_version' => $cart->version,
                'event' => isset($after[$key]) ? 'quantity_reduced' : 'line_removed', 'subject' => $row['subject'], 'quantity_delta' => Decimal::fixed($delta, 6),
                'reason' => $reason, 'snapshot' => json_encode($row, JSON_THROW_ON_ERROR), 'occurred_at' => now()]);
        }
    }
}
