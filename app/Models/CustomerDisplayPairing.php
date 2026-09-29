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
 * @property int $register_id
 * @property string $code_hash
 * @property string|null $display_token_hash
 * @property string $channel_id
 * @property string $status
 * @property CarbonImmutable $pairing_expires_at
 * @property CarbonImmutable|null $session_expires_at
 * @property CarbonImmutable|null $paired_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'created_by_user_id', 'code_hash', 'display_token_hash', 'channel_id', 'status', 'active_guard', 'pairing_expires_at', 'session_expires_at', 'paired_at', 'last_seen_at', 'revoked_at'])]
class CustomerDisplayPairing extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['pairing_expires_at' => 'immutable_datetime', 'session_expires_at' => 'immutable_datetime', 'paired_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
