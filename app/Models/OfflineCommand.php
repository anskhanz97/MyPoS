<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property int $device_enrollment_id
 * @property int|null $cash_session_id
 * @property string $command_id
 * @property string $command_type
 * @property int $schema_version
 * @property CarbonImmutable $committed_at_local
 * @property string $snapshot_version
 * @property int $fencing_epoch
 * @property string $payload_hash
 * @property array<string, mixed> $payload
 * @property string $state
 * @property int $attempt_count
 * @property string|null $resource_type
 * @property string|null $resource_public_id
 * @property string|null $conflict_code
 * @property string|null $safe_message
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $processed_at
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'device_enrollment_id', 'cash_session_id', 'command_id', 'command_type', 'schema_version', 'committed_at_local', 'snapshot_version', 'fencing_epoch', 'payload_hash', 'payload', 'state', 'attempt_count', 'resource_type', 'resource_public_id', 'conflict_code', 'safe_message', 'received_at', 'processed_at'])]
class OfflineCommand extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['schema_version' => 'integer', 'fencing_epoch' => 'integer', 'payload' => 'array', 'attempt_count' => 'integer', 'resource_public_id' => BinaryUuid::class, 'committed_at_local' => 'immutable_datetime', 'received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
