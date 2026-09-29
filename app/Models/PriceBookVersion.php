<?php

namespace App\Models;

use App\Domain\Pricing\Enums\PricingMode;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PriceBookVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int|null $store_id
 * @property int $version
 * @property PricingMode $mode
 * @property string $currency
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_until
 * @property CarbonImmutable $published_at
 * @property string $content_hash
 */
#[Fillable(['merchant_id', 'store_id', 'version', 'mode', 'currency', 'effective_from', 'effective_until', 'published_by_user_id', 'published_at', 'content_hash'])]
class PriceBookVersion extends Model
{
    /** @use HasFactory<PriceBookVersionFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<VariantPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(VariantPrice::class);
    }

    /** @return HasMany<PromotionRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(PromotionRule::class);
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'mode' => PricingMode::class, 'effective_from' => 'immutable_datetime', 'effective_until' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }
}
