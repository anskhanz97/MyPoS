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
 * @property int $device_enrollment_id
 * @property int $schema_version
 * @property string $snapshot_version
 * @property string $content_hash
 * @property array<string, mixed> $payload
 * @property string $signed_envelope
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'device_enrollment_id', 'schema_version', 'snapshot_version', 'content_hash', 'payload', 'signed_envelope', 'issued_at', 'expires_at'])]
class OfflineBootstrapSnapshot extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['schema_version' => 'integer', 'payload' => 'array', 'issued_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
