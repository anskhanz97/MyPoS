<?php

namespace App\Domain\Pricing;

use App\Domain\Catalog\CatalogScope;
use App\Domain\Pricing\Enums\PricingMode;
use App\Domain\Pricing\Enums\PromotionType;
use App\Models\Merchant;
use App\Models\PriceBookVersion;
use App\Models\ProductVariant;
use App\Models\PromotionRule;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use App\Models\VariantPrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PublishPricingVersion
{
    public function __construct(private readonly CatalogScope $catalogScope) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Merchant $merchant, Store $contextStore, array $data): PriceBookVersion
    {
        $scopeStore = $data['scope'] === 'store' ? $contextStore : null;
        if ($merchant->store_data_mode->value === 'independent') {
            abort_unless($scopeStore !== null, 422, 'Independent-store catalogs require store-scoped pricing versions.');
        }

        return DB::transaction(function () use ($actor, $merchant, $contextStore, $scopeStore, $data): PriceBookVersion {
            $current = PriceBookVersion::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStore?->id)->lockForUpdate()->orderByDesc('version')->first();
            $currentVersion = $current instanceof PriceBookVersion ? $current->version : 0;
            abort_unless($currentVersion === (int) $data['expected_version'], 409, 'The pricing version changed. Reload before publishing.');
            $mode = PricingMode::from((string) $data['mode']);
            abort_if($mode === PricingMode::Manual && ($data['rules'] ?? []) !== [], 422, 'Manual pricing mode cannot publish automatic promotion rules.');
            $normalized = ['mode' => $mode->value, 'effective_from' => $data['effective_from'], 'effective_until' => $data['effective_until'] ?? null, 'prices' => $data['prices'], 'rules' => $data['rules'] ?? []];
            $version = PriceBookVersion::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $scopeStore?->id, 'version' => $currentVersion + 1,
                'mode' => $mode, 'currency' => 'PKR', 'effective_from' => $data['effective_from'], 'effective_until' => $data['effective_until'] ?? null,
                'published_by_user_id' => $actor->id, 'published_at' => now(), 'content_hash' => hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR)),
            ]);
            $seen = [];
            $catalogStoreId = $this->catalogScope->storeId($merchant, $contextStore);
            foreach ($data['prices'] as $priceData) {
                $variant = (new ProductVariant)->resolveRouteBinding((string) $priceData['variant_id']);
                abort_unless($variant instanceof ProductVariant && $variant->merchant_id === $merchant->id && $variant->store_id === $catalogStoreId && $variant->archived_at === null, 422, 'A priced variant is outside this catalog.');
                $key = $variant->id.'|'.mb_strtolower((string) ($priceData['customer_group'] ?? ''));
                abort_if(isset($seen[$key]), 422, 'A variant/customer-group price occurs more than once.');
                $seen[$key] = true;
                VariantPrice::query()->create([
                    'merchant_id' => $merchant->id, 'price_book_version_id' => $version->id, 'product_variant_id' => $variant->id,
                    'customer_group' => $priceData['customer_group'] ?? null, 'unit_price' => $priceData['unit_price'],
                    'tax_rate_percent' => $priceData['tax_rate_percent'] ?? '0', 'tax_inclusive' => $priceData['tax_inclusive'] ?? false,
                ]);
            }
            $codes = [];
            foreach ($data['rules'] ?? [] as $ruleData) {
                $type = PromotionType::from((string) $ruleData['type']);
                $code = isset($ruleData['code']) ? mb_strtoupper((string) $ruleData['code']) : null;
                abort_if($type === PromotionType::Coupon && $code === null, 422, 'Coupon rules require a code.');
                abort_if($type !== PromotionType::Coupon && $code !== null, 422, 'Only coupon rules may define a code.');
                abort_if($code !== null && isset($codes[$code]), 422, 'Coupon codes must be unique in a pricing version.');
                abort_if(isset($ruleData['starts_at'], $ruleData['ends_at']) && CarbonImmutable::parse((string) $ruleData['ends_at']) <= CarbonImmutable::parse((string) $ruleData['starts_at']), 422, 'A promotion end must be after its start.');
                if ($code !== null) {
                    $codes[$code] = true;
                }
                $this->validateRuleShape($type, $ruleData['conditions'], $ruleData['reward']);
                foreach (['variant_ids', 'qualifying_variant_ids', 'reward_variant_ids'] as $key) {
                    foreach ($ruleData['conditions'][$key] ?? [] as $publicId) {
                        $variant = (new ProductVariant)->resolveRouteBinding((string) $publicId);
                        abort_unless($variant instanceof ProductVariant && $variant->merchant_id === $merchant->id && $variant->store_id === $catalogStoreId && $variant->archived_at === null, 422, 'A promotion variant is outside this catalog.');
                    }
                }
                PromotionRule::query()->create([
                    'merchant_id' => $merchant->id, 'price_book_version_id' => $version->id, 'type' => $type, 'name' => $ruleData['name'], 'code' => $code,
                    'priority' => $ruleData['priority'], 'stackable' => $ruleData['stackable'], 'exclusive_group' => $ruleData['exclusive_group'] ?? null,
                    'starts_at' => $ruleData['starts_at'] ?? null, 'ends_at' => $ruleData['ends_at'] ?? null, 'conditions' => $ruleData['conditions'], 'reward' => $ruleData['reward'],
                    'maximum_redemptions' => $ruleData['maximum_redemptions'] ?? null, 'maximum_per_customer' => $ruleData['maximum_per_customer'] ?? null,
                ]);
            }
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $scopeStore?->id, 'actor_user_id' => $actor->id, 'event_code' => 'pricing.version.published', 'outcome' => 'succeeded', 'metadata' => ['version_id' => $version->public_id, 'version' => $version->version, 'mode' => $mode->value, 'scope' => $scopeStore === null ? 'merchant' : 'store'], 'occurred_at' => now()]);

            return $version->load(['prices.variant.product', 'rules']);
        });
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     */
    private function validateRuleShape(PromotionType $type, array $conditions, array $reward): void
    {
        abort_unless(isset($reward['type'], $reward['value']) && in_array($reward['type'], ['percentage', 'fixed_unit', 'fixed_cart', 'fixed_price', 'free'], true) && is_numeric($reward['value']), 422, 'A promotion reward is invalid.');
        abort_if((float) $reward['value'] < 0, 422, 'A promotion reward cannot be negative.');
        abort_if($reward['type'] === 'percentage' && (float) $reward['value'] > 100, 422, 'A percentage reward cannot exceed 100.');
        $variantKeys = ['variant_ids', 'qualifying_variant_ids', 'reward_variant_ids'];
        foreach ($variantKeys as $key) {
            if (isset($conditions[$key])) {
                abort_unless(is_array($conditions[$key]) && $conditions[$key] !== [], 422, 'Promotion variant lists cannot be empty.');
            }
        }
        if ($type === PromotionType::QuantityDiscount) {
            abort_unless(isset($conditions['variant_ids'], $conditions['minimum_quantity']), 422, 'Quantity discounts require variants and a threshold.');
            abort_unless(is_numeric($conditions['minimum_quantity']) && (float) $conditions['minimum_quantity'] > 0, 422, 'A quantity threshold must be positive.');
        }
        if ($type === PromotionType::MixAndMatch) {
            abort_unless(isset($conditions['variant_ids'], $conditions['minimum_quantity']), 422, 'Mix-and-match rules require variants and a threshold.');
            abort_unless(is_numeric($conditions['minimum_quantity']) && (float) $conditions['minimum_quantity'] > 0, 422, 'A mix-and-match threshold must be positive.');
        }
        if ($type === PromotionType::BuyXGetY) {
            abort_unless(isset($conditions['qualifying_variant_ids'], $conditions['reward_variant_ids'], $conditions['buy_quantity'], $conditions['get_quantity']), 422, 'Buy-X-get-Y rules require qualifying and reward quantities.');
            abort_unless(is_numeric($conditions['buy_quantity']) && (float) $conditions['buy_quantity'] > 0 && is_numeric($conditions['get_quantity']) && (float) $conditions['get_quantity'] > 0, 422, 'Buy-X-get-Y quantities must be positive.');
        }
    }
}
