<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\CouponUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'promotion_rule_id', 'client_transaction_id', 'customer_reference', 'status', 'discount_amount', 'finalized_at', 'reversed_at'])]
class CouponUsage extends Model
{
    /** @use HasFactory<CouponUsageFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<PromotionRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class, 'promotion_rule_id');
    }

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2', 'finalized_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime'];
    }
}
