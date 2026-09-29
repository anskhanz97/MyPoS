<?php

namespace App\Domain\Sales;

use App\Domain\Auth\Actions\ConsumeApproval;
use App\Domain\Auth\Enums\Permission;
use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Cash\PostCashMovement;
use App\Domain\Devices\SettingsResolver;
use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Loyalty\LoyaltyService;
use App\Domain\Shared\Math\Decimal;
use App\Models\CashSession;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\ProductVariant;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\SaleReturnStockEffect;
use App\Models\SaleReturnTender;
use App\Models\SecurityAuditEvent;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostSaleReturn
{
    public function __construct(
        private readonly SettingsResolver $settings,
        private readonly ConsumeApproval $approvals,
        private readonly PostInventoryValuation $valuation,
        private readonly PostStockMovement $stock,
        private readonly PostCashMovement $cash,
        private readonly LoyaltyService $loyalty,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     * @return array{return: SaleReturn, replayed: bool}
     */
    public function handle(Merchant $merchant, Store $store, Register $register, Sale $sale, User $actor, string $idempotencyKey, array $data, ?string $approvalToken = null): array
    {
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $store, $register, $sale, $actor, $idempotencyKey, $data, $approvalToken, $hash): array {
            Register::query()->lockForUpdate()->findOrFail($register->id);
            $existing = SaleReturn::query()->where('merchant_id', $merchant->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof SaleReturn) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The return idempotency key was reused with different data.');
                }

                return ['return' => $this->load($existing), 'replayed' => true];
            }
            abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);
            $lockedSale = Sale::query()->with(['lines', 'tenders'])->lockForUpdate()->findOrFail($sale->id);
            abort_unless($lockedSale->merchant_id === $merchant->id && $lockedSale->store_id === $store->id, 404);

            $session = (new CashSession)->resolveRouteBinding((string) $data['register_session_id']);
            abort_unless($session instanceof CashSession && $session->merchant_id === $merchant->id && $session->store_id === $store->id && $session->register_id === $register->id, 422, 'The cash session is outside this register.');
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($session->status === CashSessionStatus::Open, 409, 'Returns must be posted into the active cash session.');

            $policyDays = (int) data_get($this->settings->current($merchant, $store), 'payload.returns.window_days', 7);
            $policyDays = max(1, min(30, $policyDays));
            $late = now()->isAfter($lockedSale->finalized_at->addDays($policyDays));
            $approval = null;
            if ($late) {
                abort_unless(trim((string) ($data['late_reason'] ?? '')) !== '', 422, 'A late-return reason is required.');
                $approval = $this->approvals->handle((string) $approvalToken, $merchant->id, $store->id, Permission::ReturnCreate, 'sale_return', $idempotencyKey);
            }

            $lineIds = [];
            foreach ($data['lines'] as $command) {
                $resolved = (new SaleLine)->resolveRouteBinding((string) $command['sale_line_id']);
                abort_unless($resolved instanceof SaleLine && $resolved->sale_id === $lockedSale->id, 422, 'A selected return line is outside the original sale.');
                $lineIds[(string) $command['sale_line_id']] = $resolved->id;
            }
            $lines = SaleLine::query()->where('sale_id', $lockedSale->id)->whereIn('id', array_values($lineIds))->lockForUpdate()->get()->keyBy('id');

            $computed = [];
            $totals = ['pre_discount' => BigDecimal::zero(), 'discount' => BigDecimal::zero(), 'net' => BigDecimal::zero(), 'tax' => BigDecimal::zero(), 'gross' => BigDecimal::zero()];
            foreach ($data['lines'] as $index => $command) {
                /** @var SaleLine $line */
                $line = $lines[$lineIds[(string) $command['sale_line_id']]];
                $quantity = BigDecimal::of((string) $command['quantity'])->toScale(6);
                abort_unless($quantity->isPositive(), 422, 'Return quantities must be positive.');
                $priorQuantity = BigDecimal::of((string) SaleReturnLine::query()->where('sale_line_id', $line->id)->sum('quantity_uom'));
                $cumulative = $priorQuantity->plus($quantity);
                $originalQuantity = BigDecimal::of($line->quantity_uom);
                abort_if($cumulative->isGreaterThan($originalQuantity), 409, 'The return exceeds the remaining quantity for an original sale line.');
                $amounts = [
                    'pre_discount' => $this->amountDelta($line->id, 'pre_discount', $line->pre_discount, $cumulative, $originalQuantity),
                    'discount' => $this->amountDelta($line->id, 'discount', $line->discount, $cumulative, $originalQuantity),
                    'net' => $this->amountDelta($line->id, 'net', $line->net, $cumulative, $originalQuantity),
                    'tax' => $this->amountDelta($line->id, 'tax', $line->tax, $cumulative, $originalQuantity),
                    'gross' => $this->amountDelta($line->id, 'gross', $line->gross, $cumulative, $originalQuantity),
                ];
                $totals['pre_discount'] = $totals['pre_discount']->plus($amounts['pre_discount']);
                $totals['discount'] = $totals['discount']->plus($amounts['discount']);
                $totals['net'] = $totals['net']->plus($amounts['net']);
                $totals['tax'] = $totals['tax']->plus($amounts['tax']);
                $totals['gross'] = $totals['gross']->plus($amounts['gross']);
                $computed[] = ['line' => $line, 'quantity' => $quantity, 'cumulative' => $cumulative, 'disposition' => $command['disposition'], 'amounts' => $amounts, 'sequence' => $index + 1];
            }
            abort_unless($totals['gross']->isPositive(), 422, 'The return total must be positive.');

            $tenderTotal = BigDecimal::zero();
            $cashTotal = BigDecimal::zero();
            $pending = false;
            foreach ($data['refund_tenders'] as $tender) {
                $amount = BigDecimal::of((string) $tender['amount'])->toScale(2);
                abort_unless($amount->isPositive(), 422, 'Refund tender amounts must be positive.');
                $tenderTotal = $tenderTotal->plus($amount);
                if ($tender['type'] === 'cash') {
                    abort_unless($tender['state'] === 'refunded' && ($tender['confirmation_source'] ?? 'not_applicable') === 'not_applicable' && empty($tender['external_reference']), 422, 'Cash refunds cannot carry external confirmation data.');
                    $cashTotal = $cashTotal->plus($amount);
                } elseif ($tender['state'] === 'refunded') {
                    abort_unless(($tender['confirmation_source'] ?? null) === 'staff_declaration' && trim((string) ($tender['external_reference'] ?? '')) !== '', 422, 'Completed external refunds require their actual confirmation reference.');
                } else {
                    abort_unless($tender['state'] === 'pending_external_action' && empty($tender['external_reference']), 422, 'Pending external refunds cannot claim a confirmation reference.');
                    $pending = true;
                }
            }
            abort_unless($tenderTotal->isEqualTo($totals['gross']), 422, 'Refund tenders must exactly equal the original-price return entitlement.');
            if ($cashTotal->isPositive()) {
                abort_unless($session->current_custodian_user_id === $actor->id, 409, 'Only the active drawer custodian can post a cash refund.');
            }

            $now = now();
            $return = SaleReturn::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'register_id' => $register->id, 'cash_session_id' => $session->id, 'sale_id' => $lockedSale->id,
                'created_by_user_id' => $actor->id, 'approval_id' => $approval?->id, 'number' => 'RET-'.mb_strtoupper(substr((string) Str::ulid(), -12)),
                'state' => $pending ? 'refund_pending' : 'posted', 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash, 'reason' => trim((string) $data['reason']),
                'late_reason' => $late ? trim((string) $data['late_reason']) : null, 'policy_window_days' => $policyDays, 'late_exception' => $late,
                'business_date' => $session->business_date, 'subtotal' => Decimal::fixed($totals['pre_discount'], 2), 'discount_total' => Decimal::fixed($totals['discount'], 2),
                'net_total' => Decimal::fixed($totals['net'], 2), 'tax_total' => Decimal::fixed($totals['tax'], 2), 'total' => Decimal::fixed($totals['gross'], 2), 'receipt_payload' => [], 'posted_at' => $now,
            ]);

            foreach ($computed as $row) {
                $line = SaleReturnLine::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'sale_return_id' => $return->id, 'sale_line_id' => $row['line']->id, 'sequence' => $row['sequence'],
                    'disposition' => $row['disposition'], 'quantity_uom' => Decimal::fixed($row['quantity'], 6),
                    'quantity_base' => Decimal::fixed($row['quantity']->multipliedBy($row['line']->factor_to_base), 6), 'cumulative_quantity_uom' => Decimal::fixed($row['cumulative'], 6),
                    'pre_discount' => Decimal::fixed($row['amounts']['pre_discount'], 2), 'discount' => Decimal::fixed($row['amounts']['discount'], 2), 'net' => Decimal::fixed($row['amounts']['net'], 2),
                    'tax' => Decimal::fixed($row['amounts']['tax'], 2), 'gross' => Decimal::fixed($row['amounts']['gross'], 2), 'original_allocations' => $row['line']->allocations,
                ]);
                if ($row['disposition'] === 'restock') {
                    $this->restock($store, $lockedSale, $row['line'], $line, $row['cumulative'], $actor, $now->toAtomString());
                }
            }
            foreach ($data['refund_tenders'] as $tender) {
                SaleReturnTender::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'sale_return_id' => $return->id, 'client_tender_id' => $tender['tender_id'], 'type' => $tender['type'],
                    'state' => $tender['state'], 'amount' => $tender['amount'], 'external_reference' => $tender['external_reference'] ?? null,
                    'confirmation_source' => $tender['type'] === 'cash' ? 'not_applicable' : ($tender['confirmation_source'] ?? 'pending'),
                    'confirmed_by_user_id' => $tender['state'] === 'refunded' && $tender['type'] !== 'cash' ? $actor->id : null,
                    'confirmed_at' => $tender['state'] === 'refunded' && $tender['type'] !== 'cash' ? $now : null,
                ]);
            }
            if ($cashTotal->isPositive()) {
                $this->cash->handle($session, $actor, $idempotencyKey, CashMovementKind::RefundCash, Decimal::fixed($cashTotal, 2), 'Return '.$return->number.' for '.$lockedSale->number, $now->toAtomString(), 'sale_return', $return->public_id, true);
            }

            $remaining = SaleLine::query()->where('sale_id', $lockedSale->id)->get()->contains(function (SaleLine $line): bool {
                return BigDecimal::of((string) SaleReturnLine::query()->where('sale_line_id', $line->id)->sum('quantity_uom'))->isLessThan(BigDecimal::of($line->quantity_uom));
            });
            $lockedSale->forceFill(['state' => $remaining ? 'partially_returned' : 'fully_returned'])->save();
            $return->forceFill(['receipt_payload' => $this->receipt($return->load(['sale', 'lines.saleLine', 'tenders', 'register.store.merchant']))])->save();
            $this->loyalty->reverseReturn($return->load('sale'), $actor);
            $this->events($return, $lockedSale, $actor, $now);

            return ['return' => $this->load($return), 'replayed' => false];
        }, attempts: 3);
    }

    private function restock(Store $store, Sale $sale, SaleLine $saleLine, SaleReturnLine $returnLine, BigDecimal $cumulative, User $actor, string $when): void
    {
        $movements = StockMovement::query()->with(['location', 'variant', 'batch'])
            ->where('source_type', 'sale')->where('source_reference', $sale->public_id)
            ->where('source_line_reference', 'like', $saleLine->public_id.'%')->lockForUpdate()->get();
        foreach ($movements as $movement) {
            if ($movement->batch !== null && $movement->batch->expiry_date->isBefore(now($store->timezone)->startOfDay())) {
                throw ValidationException::withMessages(['lines' => 'An expired original batch cannot be restocked. Choose a non-restock disposition.']);
            }
        }
        foreach ($movements->groupBy('product_variant_id') as $variantMovements) {
            /** @var StockMovement $first */
            $first = $variantMovements->first();
            /** @var list<array{movement: StockMovement, quantity: BigDecimal}> $pieces */
            $pieces = [];
            foreach ($variantMovements as $movement) {
                $entitled = BigDecimal::of($movement->quantity_base)->abs()->multipliedBy($cumulative)->dividedBy(BigDecimal::of($saleLine->quantity_uom), 6, RoundingMode::HalfUp);
                $already = BigDecimal::of((string) SaleReturnStockEffect::query()->where('original_stock_movement_id', $movement->id)->sum('quantity_base'));
                $delta = $entitled->minus($already)->toScale(6);
                if ($delta->isPositive()) {
                    $pieces[] = ['movement' => $movement, 'quantity' => $delta];
                }
            }
            if ($pieces === []) {
                continue;
            }
            $quantity = BigDecimal::zero();
            foreach ($pieces as $piece) {
                $quantity = $quantity->plus($piece['quantity']);
            }
            $unitCost = $first->unit_cost ?? '0.000000';
            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->with('product')->findOrFail($first->product_variant_id);
            $firstPiece = array_shift($pieces);
            $origin = $firstPiece['movement'];
            $originQuantity = $firstPiece['quantity'];
            $returnDocument = $returnLine->saleReturn;
            $result = $this->valuation->inbound($store, $variant, InventoryCostEntryKind::SaleReturn, Decimal::fixed($quantity, 6), $unitCost, 'sale_return', $returnDocument->public_id, $returnLine->public_id.':variant:'.$variant->public_id, $when,
                fn (string $cost, $state) => $this->stock->handle(new StockMovementCommand(location: $origin->location, variant: $variant, kind: InventoryMovementKind::Return, quantityBase: Decimal::fixed($originQuantity, 6), sourceType: 'sale_return', sourceReference: $returnDocument->public_id, sourceLineReference: $returnLine->public_id.':movement:'.$origin->public_id, batch: $origin->batch, actor: $actor, unitCost: $cost, costState: $state, occurredAt: now()->parse($when)->toImmutable()))['movement']);
            SaleReturnStockEffect::query()->create(['merchant_id' => $returnLine->merchant_id, 'store_id' => $returnLine->store_id, 'sale_return_line_id' => $returnLine->id, 'original_stock_movement_id' => $origin->id, 'return_stock_movement_id' => $result['movement']->id, 'quantity_base' => Decimal::fixed($originQuantity, 6)]);
            foreach ($pieces as $piece) {
                $originMovement = $piece['movement'];
                $pieceQuantity = $piece['quantity'];
                $posted = $this->stock->handle(new StockMovementCommand(location: $originMovement->location, variant: $variant, kind: InventoryMovementKind::Return, quantityBase: Decimal::fixed($pieceQuantity, 6), sourceType: 'sale_return', sourceReference: $returnDocument->public_id, sourceLineReference: $returnLine->public_id.':movement:'.$originMovement->public_id, batch: $originMovement->batch, actor: $actor, unitCost: $result['entry']->unit_cost, costState: $result['entry']->cost_state, occurredAt: now()->parse($when)->toImmutable()));
                SaleReturnStockEffect::query()->create(['merchant_id' => $returnLine->merchant_id, 'store_id' => $returnLine->store_id, 'sale_return_line_id' => $returnLine->id, 'original_stock_movement_id' => $originMovement->id, 'return_stock_movement_id' => $posted['movement']->id, 'quantity_base' => Decimal::fixed($pieceQuantity, 6)]);
            }
        }
    }

    private function amountDelta(int $saleLineId, string $component, string $originalAmount, BigDecimal $cumulativeQuantity, BigDecimal $originalQuantity): BigDecimal
    {
        $cumulativeEntitlement = BigDecimal::of($originalAmount)->multipliedBy($cumulativeQuantity)->dividedBy($originalQuantity, 2, RoundingMode::HalfUp);
        $already = BigDecimal::of((string) SaleReturnLine::query()->where('sale_line_id', $saleLineId)->sum($component));

        return $cumulativeEntitlement->minus($already)->toScale(2);
    }

    private function events(SaleReturn $return, Sale $sale, User $actor, CarbonInterface $when): void
    {
        foreach (['sales.return.posted', 'loyalty.sale.returned', 'fiscal.sale.returned'] as $type) {
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $return->merchant_id, 'store_id' => $return->store_id, 'event_type' => $type, 'aggregate_type' => 'sale_return', 'aggregate_id' => $return->public_id, 'payload' => ['return_id' => $return->public_id, 'sale_id' => $sale->public_id, 'total' => $return->total, 'state' => $return->state], 'occurred_at' => $when, 'available_at' => $when]);
        }
        SecurityAuditEvent::query()->create(['merchant_id' => $return->merchant_id, 'store_id' => $return->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'sales.return.posted', 'outcome' => 'succeeded', 'metadata' => ['return_id' => $return->public_id, 'sale_id' => $sale->public_id, 'late_exception' => $return->late_exception, 'state' => $return->state], 'occurred_at' => $when]);
    }

    /** @return array<string,mixed> */
    private function receipt(SaleReturn $return): array
    {
        return ['document_type' => 'sale_return', 'return_id' => $return->public_id, 'number' => $return->number, 'original_sale' => ['id' => $return->sale->public_id, 'number' => $return->sale->number], 'posted_at' => $return->posted_at->toAtomString(), 'business_date' => $return->business_date->toDateString(), 'currency' => 'PKR', 'merchant' => ['name' => $return->register->store->merchant->name], 'store' => ['name' => $return->register->store->name], 'register' => ['name' => $return->register->name], 'reason' => $return->reason, 'state' => $return->state, 'lines' => $return->lines->map(fn (SaleReturnLine $line) => ['description' => $line->saleLine->product_name_snapshot, 'sku' => $line->saleLine->sku_snapshot, 'quantity' => $line->quantity_uom, 'unit' => $line->saleLine->unit_symbol_snapshot, 'disposition' => $line->disposition, 'discount' => $line->discount, 'tax' => $line->tax, 'gross' => $line->gross])->all(), 'tenders' => $return->tenders->map(fn (SaleReturnTender $tender) => ['type' => $tender->type, 'state' => $tender->state, 'amount' => $tender->amount, 'external_reference' => $tender->external_reference])->all(), 'totals' => ['subtotal' => $return->subtotal, 'discount' => $return->discount_total, 'net' => $return->net_total, 'tax' => $return->tax_total, 'gross' => $return->total], 'fiscal_state' => 'pending_t035'];
    }

    private function load(SaleReturn $return): SaleReturn
    {
        return $return->load(['sale', 'lines.saleLine', 'tenders', 'exchange.newSale', 'register']);
    }
}
