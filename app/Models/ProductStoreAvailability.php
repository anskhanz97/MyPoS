<?php

namespace App\Models;

use Database\Factories\ProductStoreAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'store_id', 'product_id', 'is_available'])]
class ProductStoreAvailability extends Model
{
    /** @use HasFactory<ProductStoreAvailabilityFactory> */
    use HasFactory;

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }
}
