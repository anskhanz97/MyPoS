<?php

namespace App\Domain\Sales;

use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Cash\PostCashMovement;
use App\Domain\Catalog\BundleStructure;
use App\Domain\Checkout\Enums\CartStatus;
use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Inventory\ValidateBatchSelectionSnapshot;
use App\Domain\Loyalty\LoyaltyService;
use App\Domain\Pricing\Enums\PromotionType;
use App\Domain\Pricing\PricingVersionResolver;
use App\Domain\Shared\Math\Decimal;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\CartQuote;
use App\Models\CashSession;
use App\Models\CouponUsage;
use App\Models\FiscalConfiguration;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\ProductVariant;
use App\Models\PromotionRule;
use App\Models\ReceiptSnapshot;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineExtra;
use App\Models\SaleTender;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class FinalizeSale
{
    public function __construct(
        private readonly PricingVersionResolver $pricingVersions,
        private readonly ValidateBatchSelectionSnapshot $validateBatches,
        private readonly BundleStructure $bundles,
        private readonly PostInventoryValuation $valuation,
        private readonly PostStockMovement $stock,
        private readonly PostCashMovement $cash,
        private readonly LoyaltyService $loyalty,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{sale: Sale, replayed: bool}
     */
    public function handle(Merchant $merchant, Store $store, Register $register, User $actor, string $idempotencyKey, array $data, string $source = 'online'): array
    {
        abort_unless(in_array($source, ['online', 'offline'], true), 422, 'The sale source is unsupported.');
        if ($source === 'offline') {
            abort_if(FiscalConfiguration::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('applicability', 'required')->where('state', 'active')->where('outage_policy', 'block_sale')->exists(), 409, 'Offline fiscal-required sales are blocked until an authority-approved outage procedure is configured.');
        }
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $store, $register, $actor, $idempotencyKey, $data, $hash, $source): array {
            Register::query()->lockForUpdate()->findOrFail($register->id);
            $existing = Sale::query()->where('merchant_id', $merchant->id)->where(fn ($query) => $query->where('idempotency_key', $idempotencyKey)->orWhere('client_transaction_id', $data['client_transaction_id']))->lockForUpdate()->first();
            if ($existing instanceof Sale) {
                if (! hash_equals($existing->payload_hash, $hash) || $existing->idempotency_key !== $idempotencyKey || $existing->client_transaction_id !== $data['client_transaction_id']) {
                    throw new ConflictHttpException('The sale identity was reused with different command data.');
                }

                return ['sale' => $this->load($existing), 'replayed' => true];
            }
            abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);
            $quote = (new CartQuote)->resolveRouteBinding((string) $data['quote_id']);
            abort_unless($quote instanceof CartQuote && $quote->merchant_id === $merchant->id && $quote->store_id === $store->id && $quote->quote_version === (int) $data['quote_version'], 422, 'The quote was not found for this store and version.');
            $quote = CartQuote::query()->with(['cart.lines.variant.product', 'cart.lines.extras.option'])->lockForUpdate()->findOrFail($quote->id);
            $cart = Cart::query()->with(['lines.variant.product', 'lines.extras.option', 'lines.batchSelection.lines.batch', 'customer'])->lockForUpdate()->findOrFail($quote->cart_id);
            abort_unless($cart->register_id === $register->id && $cart->status === CartStatus::Active && $cart->version === $quote->cart_version, 409, 'The cart changed after this quote. Quote it again.');
            abort_if($quote->expires_at <= now(), 409, 'The quote expired. Quote the cart again.');
            $resolved = $this->pricingVersions->current($merchant, $store, now()->toImmutable());
            abort_unless(hash_equals($resolved['version'], $quote->pricing_version), 409, 'Pricing changed. Quote the cart again.');
            $session = (new CashSession)->resolveRouteBinding((string) $data['register_session_id']);
            abort_unless($session instanceof CashSession && $session->merchant_id === $merchant->id && $session->store_id === $store->id && $session->register_id === $register->id, 422, 'The cash session is outside this register.');
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            $allowedSessionStates = $source === 'offline'
                ? [CashSessionStatus::Open, CashSessionStatus::ClosePendingSync, CashSessionStatus::CountSubmitted, CashSessionStatus::VarianceReview]
                : [CashSessionStatus::Open];
            abort_unless(in_array($session->status, $allowedSessionStates, true), 409, 'The register cash session does not accept this sale.');

            $total = BigDecimal::of((string) $quote->payload['totals']['gross'])->toScale(2);
            $change = BigDecimal::of((string) $data['cash_change'])->toScale(2);
            abort_if($change->isNegative(), 422, 'Cash change cannot be negative.');
            $received = BigDecimal::zero();
            $cashReceived = BigDecimal::zero();
            foreach ($data['tenders'] as $tender) {
                $amount = BigDecimal::of((string) $tender['amount'])->toScale(2);
                abort_unless($amount->isPositive(), 422, 'Tender amounts must be positive.');
                $received = $received->plus($amount);
                if ($tender['type'] === 'cash') {
                    abort_unless(($tender['confirmation_source'] ?? 'not_applicable') === 'not_applicable' && empty($tender['external_reference']), 422, 'Cash does not accept an external confirmation reference.');
                    $cashReceived = $cashReceived->plus($amount);
                } else {
                    abort_unless(($tender['confirmation_source'] ?? null) === 'staff_declaration' && is_string($tender['external_reference'] ?? null) && trim($tender['external_reference']) !== '', 422, 'External tenders require a staff-declared reference.');
                }
            }
            abort_unless($received->minus($change)->isEqualTo($total), 422, 'Tenders minus cash change must exactly equal the sale total.');
            abort_unless($change->isLessThanOrEqualTo($cashReceived), 422, 'Cash change must be funded by the cash tender.');
            if (! $cashReceived->isZero()) {
                abort_unless($session->current_custodian_user_id === $actor->id, 409, 'Only the current drawer custodian can finalize a cash tender.');
            }

            $when = now();
            $sale = Sale::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'register_id' => $register->id, 'cash_session_id' => $session->id, 'cart_id' => $cart->id, 'cart_quote_id' => $quote->id, 'customer_id' => $cart->customer_id, 'created_by_user_id' => $actor->id, 'number' => 'SALE-'.mb_strtoupper(substr((string) Str::ulid(), -12)), 'state' => 'finalized', 'source' => $source, 'client_transaction_id' => $data['client_transaction_id'], 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash, 'pricing_version' => $quote->pricing_version, 'cart_version' => $cart->version, 'quote_version' => $quote->quote_version, 'business_date' => $session->business_date, 'subtotal' => $quote->payload['totals']['pre_discount'], 'discount_total' => $quote->payload['totals']['discount'], 'net_total' => $quote->payload['totals']['net'], 'tax_total' => $quote->payload['totals']['tax'], 'total' => $quote->payload['totals']['gross'], 'cash_change' => Decimal::fixed($change, 2), 'customer_snapshot' => $cart->customer === null ? null : ['id' => $cart->customer->public_id, 'name' => $cart->customer->name, 'phone' => $cart->customer->phone, 'email' => $cart->customer->email], 'finalized_at' => $when]);
            $quoted = [];
            foreach ($quote->payload['lines'] as $quotedLine) {
                if (is_array($quotedLine) && is_string($quotedLine['line_id'] ?? null)) {
                    $quoted[$quotedLine['line_id']] = $quotedLine;
                }
            }
            foreach ($cart->lines as $cartLine) {
                $row = $quoted[$cartLine->public_id] ?? null;
                abort_unless(is_array($row), 409, 'The quote no longer matches the cart lines.');
                $line = SaleLine::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'sale_id' => $sale->id, 'product_variant_id' => $cartLine->product_variant_id, 'unit_id' => $cartLine->unit_id, 'batch_selection_snapshot_id' => $cartLine->batch_selection_snapshot_id, 'sequence' => $cartLine->sequence, 'product_name_snapshot' => $cartLine->product_name_snapshot, 'variant_name_snapshot' => $cartLine->variant_name_snapshot, 'sku_snapshot' => $cartLine->sku_snapshot, 'unit_symbol_snapshot' => $cartLine->unit_symbol_snapshot, 'quantity_uom' => $cartLine->quantity_uom, 'factor_to_base' => $cartLine->factor_to_base, 'quantity_base' => $cartLine->quantity_base, 'unit_price' => $row['unit_price'], 'pre_discount' => $row['pre_discount'], 'discount' => $row['discount'], 'net' => $row['net'], 'tax' => $row['tax'], 'gross' => $row['gross'], 'allocations' => $row['allocations']]);
                foreach ($cartLine->extras as $extra) {
                    SaleLineExtra::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'sale_line_id' => $line->id, 'extra_option_id' => $extra->extra_option_id, 'group_name_snapshot' => $extra->group_name_snapshot, 'option_name_snapshot' => $extra->option_name_snapshot, 'unit_price_delta' => $extra->unit_price_delta, 'stock_variant_id' => $extra->option->stock_variant_id, 'stock_quantity_base' => $extra->option->stock_quantity_base, 'sequence' => $extra->sequence]);
                }
                $this->postLineInventory($store, $cart, $cartLine, $line, $sale, $actor, $when->toAtomString());
                foreach ($cartLine->extras as $extra) {
                    if ($extra->option->stock_variant_id !== null && $extra->option->stock_quantity_base !== null) {
                        $variant = ProductVariant::query()->with('product')->findOrFail($extra->option->stock_variant_id);
                        $quantity = BigDecimal::of($extra->option->stock_quantity_base)->multipliedBy($cartLine->quantity_uom)->toScale(6)->__toString();
                        $this->postVariant($store, $cart, $variant, $quantity, $sale, $line->public_id.':extra:'.$extra->public_id, $actor, $when->toAtomString());
                    }
                }
            }
            foreach ($data['tenders'] as $tender) {
                SaleTender::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'sale_id' => $sale->id, 'client_tender_id' => $tender['tender_id'], 'type' => $tender['type'], 'state' => 'accepted', 'amount' => $tender['amount'], 'external_reference' => $tender['external_reference'] ?? null, 'confirmation_source' => $tender['type'] === 'cash' ? 'not_applicable' : 'staff_declaration', 'confirmed_by_user_id' => $tender['type'] === 'cash' ? null : $actor->id, 'confirmed_at' => $tender['type'] === 'cash' ? null : $when]);
            }
            $this->claimCoupons($merchant, $sale, $quote, $cart);
            $drawer = $cashReceived->minus($change);
            if ($drawer->isPositive()) {
                $this->cash->handle($session, $actor, $idempotencyKey, CashMovementKind::SaleCash, Decimal::fixed($drawer, 2), 'Sale '.$sale->number, $when->toAtomString(), 'sale', $sale->public_id, true);
            }
            $receiptPayload = $this->receiptPayload($sale->load(['lines.extras', 'tenders']));
            ReceiptSnapshot::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'sale_id' => $sale->id, 'version' => 1, 'content_hash' => hash('sha256', json_encode($receiptPayload, JSON_THROW_ON_ERROR)), 'payload' => $receiptPayload, 'created_at' => $when]);
            $this->loyalty->commit($sale, $cart, $quote->payload, $actor, $source);
            $this->loyalty->earn($sale, $actor);
            $cart->forceFill(['status' => CartStatus::Converted])->save();
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'store_id' => $store->id, 'event_type' => 'sales.sale.finalized', 'aggregate_type' => 'sale', 'aggregate_id' => $sale->public_id, 'payload' => ['sale_id' => $sale->public_id, 'store_id' => $store->public_id, 'register_id' => $register->public_id, 'cash_session_id' => $session->public_id, 'business_date' => $sale->business_date->toDateString(), 'total' => $sale->total, 'source' => $source], 'occurred_at' => $when, 'available_at' => $when]);
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'sales.sale.finalized', 'outcome' => 'succeeded', 'metadata' => ['sale_id' => $sale->public_id, 'client_transaction_id' => $sale->client_transaction_id], 'occurred_at' => $when]);

            return ['sale' => $this->load($sale), 'replayed' => false];
        }, attempts: 3);
    }

    private function postLineInventory(Store $store, Cart $cart, CartLine $cartLine, SaleLine $line, Sale $sale, User $actor, string $when): void
    {
        if ($cartLine->batchSelection !== null) {
            $this->validateBatches->handle($cartLine->batchSelection, $store);
            $parts = $cartLine->batchSelection->lines;
            $first = $parts->shift();
            abort_unless($first !== null, 409, 'The batch selection has no allocations.');
            $result = $this->valuation->outbound($store, $cartLine->variant, InventoryCostEntryKind::SaleIssue, $cartLine->quantity_base, 'sale', $sale->public_id, $line->public_id, $when, function (?string $cost, $state) use ($cart, $cartLine, $first, $line, $sale, $actor, $when) {
                return $this->stock->handle(new StockMovementCommand(location: $cart->location, variant: $cartLine->variant, kind: InventoryMovementKind::Sale, quantityBase: BigDecimal::of($first->quantity_base)->negated()->toScale(6)->__toString(), sourceType: 'sale', sourceReference: $sale->public_id, sourceLineReference: $line->public_id.':batch:'.$first->sequence, batch: $first->batch, actor: $actor, unitCost: $cost, costState: $state, occurredAt: now()->parse($when)->toImmutable()))['movement'];
            });
            foreach ($parts as $part) {
                $this->stock->handle(new StockMovementCommand(location: $cart->location, variant: $cartLine->variant, kind: InventoryMovementKind::Sale, quantityBase: BigDecimal::of($part->quantity_base)->negated()->toScale(6)->__toString(), sourceType: 'sale', sourceReference: $sale->public_id, sourceLineReference: $line->public_id.':batch:'.$part->sequence, batch: $part->batch, actor: $actor, unitCost: $result['entry']->unit_cost, costState: $result['entry']->cost_state, occurredAt: now()->parse($when)->toImmutable()));
            }

            return;
        }
        foreach ($this->bundles->expand($cartLine->variant, $cartLine->quantity_base) as $variantId => $quantity) {
            $variant = ProductVariant::query()->with('product')->findOrFail($variantId);
            $this->postVariant($store, $cart, $variant, $quantity, $sale, $line->public_id.':variant:'.$variant->public_id, $actor, $when);
        }
    }

    private function postVariant(Store $store, Cart $cart, ProductVariant $variant, string $quantity, Sale $sale, string $sourceLine, User $actor, string $when): void
    {
        $this->valuation->outbound($store, $variant, InventoryCostEntryKind::SaleIssue, $quantity, 'sale', $sale->public_id, $sourceLine, $when, function (?string $cost, $state) use ($cart, $variant, $quantity, $sale, $sourceLine, $actor, $when) {
            return $this->stock->handle(new StockMovementCommand(location: $cart->location, variant: $variant, kind: InventoryMovementKind::Sale, quantityBase: BigDecimal::of($quantity)->negated()->toScale(6)->__toString(), sourceType: 'sale', sourceReference: $sale->public_id, sourceLineReference: $sourceLine, actor: $actor, unitCost: $cost, costState: $state, occurredAt: now()->parse($when)->toImmutable()))['movement'];
        });
    }

    private function claimCoupons(Merchant $merchant, Sale $sale, CartQuote $quote, Cart $cart): void
    {
        $amounts = [];
        foreach ($quote->payload['lines'] as $line) {
            foreach ($line['allocations'] as $allocation) {
                if (is_string($allocation['rule_id'] ?? null)) {
                    $amounts[$allocation['rule_id']] = BigDecimal::of($amounts[$allocation['rule_id']] ?? '0')->plus((string) $allocation['amount'])->toScale(2)->__toString();
                }
            }
        }
        foreach ($amounts as $ruleId => $amount) {
            $rule = (new PromotionRule)->resolveRouteBinding($ruleId);
            if (! $rule instanceof PromotionRule || $rule->type !== PromotionType::Coupon) {
                continue;
            } $rule = PromotionRule::query()->lockForUpdate()->findOrFail($rule->id);
            if ($rule->maximum_redemptions !== null) {
                abort_if(CouponUsage::query()->where('promotion_rule_id', $rule->id)->where('status', 'finalized')->count() >= $rule->maximum_redemptions, 409, 'A coupon reached its redemption limit. Quote the cart again.');
            } $customer = $cart->customer?->public_id;
            if ($rule->maximum_per_customer !== null) {
                abort_unless($customer !== null, 409, 'This coupon requires a customer.');
                abort_if(CouponUsage::query()->where('promotion_rule_id', $rule->id)->where('customer_reference', $customer)->where('status', 'finalized')->count() >= $rule->maximum_per_customer, 409, 'This customer reached the coupon limit.');
            } CouponUsage::query()->create(['merchant_id' => $merchant->id, 'promotion_rule_id' => $rule->id, 'client_transaction_id' => $sale->client_transaction_id, 'customer_reference' => $customer, 'status' => 'finalized', 'discount_amount' => $amount, 'finalized_at' => now()]);
        }
    }

    /** @return array<string,mixed> */
    private function receiptPayload(Sale $sale): array
    {
        $sale->loadMissing(['register.store.merchant']);

        return ['sale_id' => $sale->public_id, 'number' => $sale->number, 'source' => $sale->source, 'business_date' => $sale->business_date->toDateString(), 'finalized_at' => $sale->finalized_at->toAtomString(), 'currency' => 'PKR', 'merchant' => ['id' => $sale->register->store->merchant->public_id, 'name' => $sale->register->store->merchant->name], 'store' => ['id' => $sale->register->store->public_id, 'name' => $sale->register->store->name, 'code' => $sale->register->store->code], 'register' => ['id' => $sale->register->public_id, 'name' => $sale->register->name, 'code' => $sale->register->code], 'customer' => $sale->customer_snapshot, 'lines' => $sale->lines->map(fn (SaleLine $line) => ['description' => $line->product_name_snapshot, 'sku' => $line->sku_snapshot, 'quantity' => $line->quantity_uom, 'unit' => $line->unit_symbol_snapshot, 'unit_price' => $line->unit_price, 'discount' => $line->discount, 'tax' => $line->tax, 'gross' => $line->gross, 'extras' => $line->extras->map(fn (SaleLineExtra $extra) => ['name' => $extra->option_name_snapshot, 'price_delta' => $extra->unit_price_delta])->all()])->all(), 'tenders' => $sale->tenders->map(fn (SaleTender $tender) => ['type' => $tender->type, 'amount' => $tender->amount, 'external_reference' => $tender->external_reference, 'confirmation_source' => $tender->confirmation_source])->all(), 'cash_change' => $sale->cash_change, 'totals' => ['subtotal' => $sale->subtotal, 'discount' => $sale->discount_total, 'net' => $sale->net_total, 'tax' => $sale->tax_total, 'gross' => $sale->total], 'fiscal_state' => 'not_required'];
    }

    private function load(Sale $sale): Sale
    {
        return $sale->load(['lines.extras', 'tenders', 'receipt', 'register']);
    }
}
