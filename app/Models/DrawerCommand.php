<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property int $cash_session_id
 * @property int|null $print_job_id
 * @property string $kind
 * @property string $reason
 * @property string $state
 * @property string|null $helper_event_id
 * @property string|null $outcome_payload_hash
 * @property array<string, mixed> $signed_authorization
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $reported_at
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'cash_session_id', 'requested_by_user_id', 'print_job_id', 'idempotency_key', 'payload_hash', 'kind', 'reason', 'state', 'helper_event_id', 'outcome_payload_hash', 'signed_authorization', 'expires_at', 'reported_at'])]
class DrawerCommand extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['signed_authorization' => 'array', 'expires_at' => 'immutable_datetime', 'reported_at' => 'immutable_datetime'];
    }
}
