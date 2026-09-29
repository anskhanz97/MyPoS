<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\RegisterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property string $name
 * @property string $code
 * @property array<string, bool> $capabilities
 * @property int $fencing_epoch
 * @property-read Store $store
 */
#[Fillable(['merchant_id', 'store_id', 'name', 'code', 'status', 'capabilities', 'fencing_epoch'])]
class Register extends Model
{
    /** @use HasFactory<RegisterFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return HasMany<DeviceEnrollment, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(DeviceEnrollment::class);
    }

    protected function casts(): array
    {
        return ['capabilities' => 'array', 'fencing_epoch' => 'integer'];
    }
}
