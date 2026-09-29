<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $loyalty_member_id
 * @property int $loyalty_reward_id
 * @property int $cart_id
 * @property int|null $sale_id
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property string $state
 * @property string $points
 * @property string $discount_amount
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['merchant_id', 'store_id', 'loyalty_member_id', 'loyalty_reward_id', 'cart_id', 'sale_id', 'created_by_user_id', 'idempotency_key', 'payload_hash', 'state', 'points', 'discount_amount', 'expires_at', 'committed_at', 'released_at'])]
class LoyaltyRedemptionReservation extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['points' => 'decimal:4', 'discount_amount' => 'decimal:2', 'expires_at' => 'immutable_datetime', 'committed_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }
}
