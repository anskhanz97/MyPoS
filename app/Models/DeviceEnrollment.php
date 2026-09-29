<?php

namespace App\Models;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\DeviceEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property string $device_key_hash
 * @property int $credential_version
 * @property int $fencing_epoch
 * @property int $settings_version
 * @property int $protocol_min
 * @property int $protocol_max
 * @property DeviceStatus $status
 * @property CarbonImmutable $enrolled_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $lease_expires_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'name', 'device_key_hash', 'credential_version', 'fencing_epoch', 'settings_version', 'protocol_min', 'protocol_max', 'status', 'enrolled_at', 'last_seen_at', 'lease_expires_at', 'revoked_at', 'revoked_by_user_id', 'revoke_reason'])]
#[Hidden(['device_key_hash'])]
class DeviceEnrollment extends Model
{
    /** @use HasFactory<DeviceEnrollmentFactory> */
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

    /** @return BelongsTo<Register, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    protected function casts(): array
    {
        return ['status' => DeviceStatus::class, 'credential_version' => 'integer', 'fencing_epoch' => 'integer', 'settings_version' => 'integer', 'protocol_min' => 'integer', 'protocol_max' => 'integer', 'enrolled_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime', 'lease_expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
