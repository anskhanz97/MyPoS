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
 * @property int|null $store_id
 * @property int $loyalty_member_id
 * @property string $kind
 * @property string $points
 * @property string|null $eligible_net
 * @property string $source_type
 * @property string $source_reference
 * @property string|null $reason
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['merchant_id', 'store_id', 'loyalty_member_id', 'loyalty_program_version_id', 'related_entry_id', 'actor_user_id', 'kind', 'points', 'eligible_net', 'source_type', 'source_reference', 'reason', 'expires_at', 'occurred_at'])]
class LoyaltyPointEntry extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['points' => 'decimal:4', 'eligible_net' => 'decimal:2', 'expires_at' => 'immutable_datetime', 'occurred_at' => 'immutable_datetime'];
    }
}
