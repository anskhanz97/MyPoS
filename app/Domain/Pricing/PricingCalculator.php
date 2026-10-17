<?php

namespace App\Domain\Pricing;

use App\Domain\Pricing\Enums\PricingMode;
use App\Domain\Pricing\Enums\PromotionType;
use App\Domain\Shared\Math\Decimal;
use App\Models\CouponUsage;
use App\Models\Merchant;
use App\Models\PriceBookVersion;
use App\Models\ProductVariant;
use App\Models\PromotionRule;
use App\Models\Store;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type PricingLine array{line_id: string, sequence: int, variant: ProductVariant, quantity: BigDecimal, unit_price: BigDecimal, tax_rate: BigDecimal, tax_inclusive: bool, pre_discount: BigDecimal, discount: BigDecimal, allocations: list<array{source: string, rule_id: string|null, rule_name: string|null, amount: string}>, locked: bool, manual_discount: BigDecimal}
 */
final class PricingCalculator
{
    public function __construct(private readonly PricingVersionResolver $resolver, private readonly DiscountAllocator $allocator) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function quote(Merchant $merchant, Store $store, array $data): array
    {
        $at = now()->toImmutable();
        $resolved = $this->resolver->current($merchant, $store, $at);
        if (isset($data['pricing_version'])) {
            abort_unless(hash_equals($resolved['version'], (string) $data['pricing_version']), 409, 'Pricing changed. Reprice this cart before finalizing.');
        }
        $versions = array_values(array_filter([$resolved['merchant'], $resolved['store']]));
        $effective = $resolved['store'] ?? $resolved['merchant'];
        abort_unless($effective instanceof PriceBookVersion, 409);
        $mode = $effective->mode;
        $customerGroup = $data['customer_group'] ?? null;
        $priceMap = $this->priceMap($versions, is_string($customerGroup) ? $customerGroup : null);
        $lines = [];
        foreach ($data['lines'] as $sequence => $input) {
            $variant = (new ProductVariant)->resolveRouteBinding((string) $input['variant_id']);
            abort_unless($variant instanceof ProductVariant && $variant->merchant_id === $merchant->id && ($variant->store_id === null || $variant->store_id === $store->id) && $variant->archived_at === null, 422, 'A quote line variant is outside this catalog.');
            if ($variant->store_id === null) {
                abort_unless($variant->product->storeAvailabilities()->where('store_id', $store->id)->where('is_available', true)->exists(), 422, 'A quote line is unavailable in this store.');
            }
            abort_unless(isset($priceMap[$variant->id]), 422, 'No effective price exists for a quote line.');
            $price = $priceMap[$variant->id];
            $price['unit_price'] = $price['unit_price']->plus(Decimal::of((string) ($input['unit_price_delta'] ?? '0')));
            $quantity = Decimal::of((string) $input['quantity']);
            $gross = $quantity->multipliedBy($price['unit_price']);
            $lines[(string) $input['line_id']] = [
                'line_id' => (string) $input['line_id'], 'sequence' => $sequence + 1, 'variant' => $variant, 'quantity' => $quantity,
                'unit_price' => $price['unit_price'], 'tax_rate' => $price['tax_rate'], 'tax_inclusive' => $price['tax_inclusive'],
                'pre_discount' => $gross, 'discount' => BigDecimal::zero(), 'allocations' => [], 'locked' => false,
                'manual_discount' => Decimal::of((string) ($input['manual_discount'] ?? '0')),
            ];
        }
        if ($mode === PricingMode::Advanced) {
            $lines = $this->applyRules($lines, $versions, $data, $at);
        }
        $manualCart = Decimal::of((string) ($data['manual_cart_discount'] ?? '0'));
        foreach ($lines as &$line) {
            $available = $line['pre_discount']->minus($line['discount']);
            abort_if($line['manual_discount']->isGreaterThan($available), 422, 'A manual line discount exceeds the line value.');
            $line = $this->withDiscount($line, $line['manual_discount'], 'manual_line', null);
        }
        unset($line);
        $eligible = $this->allocationLines($lines);
        $availableCart = array_reduce($eligible, fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['weight']), BigDecimal::zero());
        abort_if($manualCart->isGreaterThan($availableCart), 422, 'The manual cart discount exceeds the remaining cart value.');
        foreach ($this->allocator->allocate($manualCart, $eligible) as $lineId => $amount) {
            $lines[$lineId] = $this->withDiscount($lines[$lineId], $amount, 'manual_cart', null);
        }
        $loyalty = Decimal::of((string) ($data['loyalty_discount'] ?? '0'));
        $eligible = $this->allocationLines($lines);
        $availableLoyalty = array_reduce($eligible, fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['weight']), BigDecimal::zero());
        abort_if($loyalty->isGreaterThan($availableLoyalty), 422, 'The loyalty reward exceeds the remaining cart value.');
        foreach ($this->allocator->allocate($loyalty, $eligible) as $lineId => $amount) {
            $lines[$lineId] = $this->withDiscount($lines[$lineId], $amount, 'loyalty_reward', null);
        }

        $output = [];
        $totalPre = BigDecimal::zero();
        $totalDiscount = BigDecimal::zero();
        $totalNet = BigDecimal::zero();
        $totalTax = BigDecimal::zero();
        $totalGross = BigDecimal::zero();
        foreach ($lines as $line) {
            $after = $line['pre_discount']->minus($line['discount']);
            $rate = $line['tax_rate']->dividedBy('100', Decimal::CalculatorScale, RoundingMode::HalfUp);
            if ($line['tax_inclusive']) {
                $gross = $after->toScale(2, RoundingMode::HalfUp);
                $tax = $after->multipliedBy($rate)->dividedBy(BigDecimal::one()->plus($rate), Decimal::CalculatorScale, RoundingMode::HalfUp)->toScale(2, RoundingMode::HalfUp);
                $net = $gross->minus($tax);
            } else {
                $net = $after->toScale(2, RoundingMode::HalfUp);
                $tax = $after->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp);
                $gross = $net->plus($tax);
            }
            $totalPre = $totalPre->plus($line['pre_discount']);
            $totalDiscount = $totalDiscount->plus($line['discount']);
            $totalNet = $totalNet->plus($net);
            $totalTax = $totalTax->plus($tax);
            $totalGross = $totalGross->plus($gross);
            $output[] = ['line_id' => $line['line_id'], 'variant_id' => $line['variant']->public_id, 'sku' => $line['variant']->sku, 'quantity' => Decimal::fixed($line['quantity'], 6), 'unit_price' => Decimal::fixed($line['unit_price'], 4), 'pre_discount' => Decimal::fixed($line['pre_discount'], 2), 'discount' => Decimal::fixed($line['discount'], 2), 'net' => (string) $net, 'tax' => (string) $tax, 'gross' => (string) $gross, 'tax_rate_percent' => Decimal::fixed($line['tax_rate'], 6), 'allocations' => $line['allocations']];
        }

        return ['pricing_version' => $resolved['version'], 'mode' => $mode->value, 'currency' => 'PKR', 'quoted_at' => $at->toAtomString(), 'requires_reprice_on_version_change' => true, 'coupon_consumed' => false, 'loyalty_redemption' => $data['loyalty_redemption'] ?? null, 'lines' => $output, 'totals' => ['pre_discount' => Decimal::fixed($totalPre, 2), 'discount' => Decimal::fixed($totalDiscount, 2), 'net' => Decimal::fixed($totalNet, 2), 'tax' => Decimal::fixed($totalTax, 2), 'gross' => Decimal::fixed($totalGross, 2)]];
    }

    /**
     * @param  list<PriceBookVersion>  $versions
     * @return array<int, array{unit_price: BigDecimal, tax_rate: BigDecimal, tax_inclusive: bool}>
     */
    private function priceMap(array $versions, ?string $group): array
    {
        $map = [];
        foreach ($versions as $version) {
            foreach ($version->prices->sortBy(fn ($price): int => $price->customer_group === null ? 0 : 1) as $price) {
                if ($price->customer_group !== null && mb_strtolower($price->customer_group) !== mb_strtolower((string) $group)) {
                    continue;
                }
                $map[$price->product_variant_id] = ['unit_price' => Decimal::of($price->unit_price), 'tax_rate' => Decimal::of($price->tax_rate_percent), 'tax_inclusive' => $price->tax_inclusive];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, PricingLine>  $lines
     * @param  list<PriceBookVersion>  $versions
     * @param  array<string, mixed>  $data
     * @return array<string, PricingLine>
     */
    private function applyRules(array $lines, array $versions, array $data, CarbonImmutable $at): array
    {
        $coupons = array_values(array_map(fn ($code): string => mb_strtoupper((string) $code), $data['coupon_codes'] ?? []));
        $rules = collect($versions)->flatMap->rules->filter(fn (PromotionRule $rule): bool => ($rule->starts_at === null || $rule->starts_at <= $at) && ($rule->ends_at === null || $rule->ends_at > $at))->sortBy(fn (PromotionRule $rule): string => $this->ruleStage($rule).'|'.str_pad((string) $rule->priority, 10, '0', STR_PAD_LEFT).'|'.$rule->public_id)->groupBy(fn (PromotionRule $rule): string => $this->ruleStage($rule).'|'.$rule->priority);
        foreach ($rules as $priorityRules) {
            $selected = [];
            $exclusive = [];
            foreach ($priorityRules as $rule) {
                if (! $this->couponAvailable($rule, $coupons, $data)) {
                    continue;
                }
                $targets = $this->eligibleLines($lines, $rule);
                if (! $this->thresholdMet($rule->type === PromotionType::BuyXGetY ? $lines : $targets, $rule)) {
                    continue;
                }
                $amounts = $this->rewardAmounts($targets, $rule, $lines);
                $total = array_reduce($amounts, fn (BigDecimal $sum, BigDecimal $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero());
                if ($total->isZero()) {
                    continue;
                }
                $candidate = ['rule' => $rule, 'amounts' => $amounts, 'total' => $total];
                if ($rule->exclusive_group === null) {
                    $selected[] = $candidate;

                    continue;
                }
                $existing = $exclusive[$rule->exclusive_group] ?? null;
                if ($existing === null || $total->isGreaterThan($existing['total']) || ($total->isEqualTo($existing['total']) && $rule->public_id < $existing['rule']->public_id)) {
                    $exclusive[$rule->exclusive_group] = $candidate;
                }
            }
            foreach (array_merge($selected, array_values($exclusive)) as $candidate) {
                $rule = $candidate['rule'];
                foreach ($candidate['amounts'] as $lineId => $amount) {
                    if ($lines[$lineId]['locked']) {
                        continue;
                    }
                    $lines[$lineId] = $this->withDiscount($lines[$lineId], $amount, 'promotion', $rule);
                    if (! $rule->stackable) {
                        $lines[$lineId]['locked'] = true;
                    }
                }
            }
        }

        return $lines;
    }

    private function ruleStage(PromotionRule $rule): int
    {
        return match ($rule->type) {
            PromotionType::ScheduledPrice, PromotionType::QuantityDiscount => 1,
            PromotionType::MixAndMatch, PromotionType::BuyXGetY => 2,
            PromotionType::Coupon => 3,
        };
    }

    /**
     * @param  list<string>  $coupons
     * @param  array<string, mixed>  $data
     */
    private function couponAvailable(PromotionRule $rule, array $coupons, array $data): bool
    {
        if ($rule->type !== PromotionType::Coupon) {
            return true;
        }
        if ($rule->code === null || ! in_array(mb_strtoupper($rule->code), $coupons, true)) {
            return false;
        }
        if ($rule->maximum_redemptions !== null && CouponUsage::query()->where('promotion_rule_id', $rule->id)->where('status', 'finalized')->count() >= $rule->maximum_redemptions) {
            return false;
        }
        if ($rule->maximum_per_customer !== null) {
            $customer = $data['customer_reference'] ?? null;
            abort_unless(is_string($customer) && $customer !== '', 422, 'This coupon requires a customer reference.');
            if (CouponUsage::query()->where('promotion_rule_id', $rule->id)->where('customer_reference', $customer)->where('status', 'finalized')->count() >= $rule->maximum_per_customer) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, PricingLine>  $lines
     * @return array<string, PricingLine>
     */
    private function eligibleLines(array $lines, PromotionRule $rule): array
    {
        $lines = array_filter($lines, fn (array $line): bool => ! $line['locked']);
        $conditions = $rule->conditions;
        $ids = $conditions['variant_ids'] ?? ($conditions['reward_variant_ids'] ?? []);
        if ($ids === []) {
            return $lines;
        }

        return array_filter($lines, fn (array $line): bool => in_array($line['variant']->public_id, $ids, true));
    }

    /** @param array<string, PricingLine> $targets */
    private function thresholdMet(array $targets, PromotionRule $rule): bool
    {
        $quantity = array_reduce($targets, fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['quantity']), BigDecimal::zero());
        $subtotal = array_reduce($targets, fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['pre_discount']->minus($line['discount'])), BigDecimal::zero());
        $conditions = $rule->conditions;
        if (isset($conditions['minimum_quantity']) && $quantity->isLessThan(Decimal::of((string) $conditions['minimum_quantity']))) {
            return false;
        }
        if (isset($conditions['minimum_subtotal']) && $subtotal->isLessThan(Decimal::of((string) $conditions['minimum_subtotal']))) {
            return false;
        }
        if ($rule->type === PromotionType::BuyXGetY) {
            $qualifyingIds = $conditions['qualifying_variant_ids'] ?? [];
            $qualifying = BigDecimal::zero();
            foreach ($targets as $line) {
                if (in_array($line['variant']->public_id, $qualifyingIds, true)) {
                    $qualifying = $qualifying->plus($line['quantity']);
                }
            }

            return ! $qualifying->isLessThan(Decimal::of((string) $conditions['buy_quantity']));
        }

        return true;
    }

    /**
     * @param  array<string, PricingLine>  $targets
     * @param  array<string, PricingLine>  $allLines
     * @return array<string, BigDecimal>
     */
    private function rewardAmounts(array $targets, PromotionRule $rule, array $allLines): array
    {
        $reward = $rule->reward;
        $type = (string) $reward['type'];
        $value = Decimal::of((string) $reward['value']);
        if ($rule->type === PromotionType::BuyXGetY) {
            $conditions = $rule->conditions;
            $qualifyingIds = $conditions['qualifying_variant_ids'];
            $qualifying = BigDecimal::zero();
            foreach ($allLines as $line) {
                if (in_array($line['variant']->public_id, $qualifyingIds, true)) {
                    $qualifying = $qualifying->plus($line['quantity']);
                }
            }
            $cycles = (int) (string) $qualifying->dividedBy(Decimal::of((string) $conditions['buy_quantity']), 0, RoundingMode::Floor);
            $remaining = Decimal::of((string) $conditions['get_quantity'])->multipliedBy($cycles);
            uasort($targets, fn (array $a, array $b): int => ($a['unit_price']->compareTo($b['unit_price'])) ?: ($a['sequence'] <=> $b['sequence']));
            $amounts = [];
            foreach ($targets as $lineId => $line) {
                if ($remaining->isZero()) {
                    break;
                }
                $rewardQuantity = $line['quantity']->isLessThan($remaining) ? $line['quantity'] : $remaining;
                $amounts[$lineId] = $line['unit_price']->multipliedBy($rewardQuantity)->toScale(2, RoundingMode::HalfUp);
                $remaining = $remaining->minus($rewardQuantity);
            }

            return $amounts;
        }
        if ($type === 'fixed_cart') {
            $available = $this->allocationLines($targets);
            $sum = array_reduce($available, fn (BigDecimal $carry, array $row): BigDecimal => $carry->plus($row['weight']), BigDecimal::zero());

            return $this->allocator->allocate($value->isGreaterThan($sum) ? $sum : $value, $available);
        }
        $amounts = [];
        foreach ($targets as $lineId => $line) {
            $available = $line['pre_discount']->minus($line['discount']);
            $amount = match ($type) {
                'percentage' => $available->multipliedBy($value)->dividedBy('100', Decimal::CalculatorScale, RoundingMode::HalfUp),
                'fixed_unit' => $line['quantity']->multipliedBy($value),
                'fixed_price' => $available->minus($line['quantity']->multipliedBy($value)),
                'free' => $available,
                default => BigDecimal::zero(),
            };
            if ($amount->isLessThan(0)) {
                $amount = BigDecimal::zero();
            }
            if ($amount->isGreaterThan($available)) {
                $amount = $available;
            }
            if (isset($reward['cap_amount']) && $amount->isGreaterThan(Decimal::of((string) $reward['cap_amount']))) {
                $amount = Decimal::of((string) $reward['cap_amount']);
            }
            $amounts[$lineId] = $amount->toScale(2, RoundingMode::HalfUp);
        }

        return $amounts;
    }

    /**
     * @param  PricingLine  $line
     * @return PricingLine
     */
    private function withDiscount(array $line, BigDecimal $amount, string $source, ?PromotionRule $rule): array
    {
        if ($amount->isZero()) {
            return $line;
        }
        $available = $line['pre_discount']->minus($line['discount']);
        $amount = $amount->isGreaterThan($available) ? $available : $amount;
        $line['discount'] = $line['discount']->plus($amount);
        $line['allocations'][] = ['source' => $source, 'rule_id' => $rule?->public_id, 'rule_name' => $rule?->name, 'amount' => Decimal::fixed($amount, 2)];

        return $line;
    }

    /**
     * @param  array<string, PricingLine>  $lines
     * @return list<array{line_id: string, sequence: int, weight: BigDecimal}>
     */
    private function allocationLines(array $lines): array
    {
        return array_values(array_map(fn (array $line): array => ['line_id' => $line['line_id'], 'sequence' => $line['sequence'], 'weight' => $line['pre_discount']->minus($line['discount'])], array_filter($lines, fn (array $line): bool => $line['pre_discount']->minus($line['discount'])->isGreaterThan(0))));
    }
}
