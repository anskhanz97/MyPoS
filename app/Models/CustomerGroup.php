<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int|null $store_id
 * @property string $code
 * @property string $name
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable(['merchant_id', 'store_id', 'code', 'name', 'archived_at'])]
class CustomerGroup extends Model
{
    /** @use HasFactory<CustomerGroupFactory> */
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

    /** @return HasMany<OperationalCustomer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(OperationalCustomer::class);
    }

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }
}
