<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\DevicePairingCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property int $created_by_user_id
 * @property string $code_hash
 * @property bool $replace_existing
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'created_by_user_id', 'code_hash', 'replace_existing', 'expires_at', 'consumed_at', 'revoked_at'])]
#[Hidden(['code_hash'])]
class DevicePairingCode extends Model
{
    /** @use HasFactory<DevicePairingCodeFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['replace_existing' => 'boolean', 'expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
