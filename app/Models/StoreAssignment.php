<?php

namespace App\Models;

use Database\Factories\StoreAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'merchant_membership_id', 'store_id'])]
class StoreAssignment extends Model
{
    /** @use HasFactory<StoreAssignmentFactory> */
    use HasFactory;

    /** @return BelongsTo<MerchantMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(MerchantMembership::class, 'merchant_membership_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
