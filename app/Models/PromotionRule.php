<?php

namespace App\Models;

use App\Domain\Pricing\Enums\PromotionType;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PromotionRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $price_book_version_id
 * @property PromotionType $type
 * @property string $name
 * @property string|null $code
 * @property int $priority
 * @property bool $stackable
 * @property string|null $exclusive_group
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property array<string, mixed> $conditions
 * @property array<string, mixed> $reward
 * @property int|null $maximum_redemptions
 * @property int|null $maximum_per_customer
 */
#[Fillable(['merchant_id', 'price_book_version_id', 'type', 'name', 'code', 'priority', 'stackable', 'exclusive_group', 'starts_at', 'ends_at', 'conditions', 'reward', 'maximum_redemptions', 'maximum_per_customer'])]
class PromotionRule extends Model
{
    /** @use HasFactory<PromotionRuleFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<PriceBookVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PriceBookVersion::class, 'price_book_version_id');
    }

    /** @return HasMany<CouponUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    protected function casts(): array
    {
        return ['type' => PromotionType::class, 'priority' => 'integer', 'stackable' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'conditions' => 'array', 'reward' => 'array', 'maximum_redemptions' => 'integer', 'maximum_per_customer' => 'integer'];
    }
}
