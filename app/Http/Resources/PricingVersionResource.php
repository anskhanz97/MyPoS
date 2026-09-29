<?php

namespace App\Http\Resources;

use App\Models\PriceBookVersion;
use App\Models\PromotionRule;
use App\Models\VariantPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PriceBookVersion */
class PricingVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'scope' => $this->store_id === null ? 'merchant' : 'store', 'version' => $this->version,
            'mode' => $this->mode->value, 'currency' => $this->currency, 'effective_from' => $this->effective_from->toAtomString(),
            'effective_until' => $this->effective_until?->toAtomString(), 'published_at' => $this->published_at->toAtomString(), 'content_hash' => $this->content_hash,
            'prices' => $this->whenLoaded('prices', fn () => $this->prices->map(fn (VariantPrice $price): array => ['variant_id' => $price->variant->public_id, 'sku' => $price->variant->sku, 'product_name' => $price->variant->product->name, 'customer_group' => $price->customer_group, 'unit_price' => $price->unit_price, 'tax_rate_percent' => $price->tax_rate_percent, 'tax_inclusive' => $price->tax_inclusive])->values()),
            'rules' => $this->whenLoaded('rules', fn () => $this->rules->map(fn (PromotionRule $rule): array => ['id' => $rule->public_id, 'type' => $rule->type->value, 'name' => $rule->name, 'code' => $rule->code, 'priority' => $rule->priority, 'stackable' => $rule->stackable, 'exclusive_group' => $rule->exclusive_group, 'starts_at' => $rule->starts_at?->toAtomString(), 'ends_at' => $rule->ends_at?->toAtomString(), 'conditions' => $rule->conditions, 'reward' => $rule->reward, 'maximum_redemptions' => $rule->maximum_redemptions, 'maximum_per_customer' => $rule->maximum_per_customer])->values()),
        ];
    }
}
