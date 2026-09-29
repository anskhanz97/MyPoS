<?php

namespace App\Models;

use Database\Factories\LoyaltyCustomerLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'loyalty_member_id', 'operational_customer_id'])]
class LoyaltyCustomerLink extends Model
{
    /** @use HasFactory<LoyaltyCustomerLinkFactory> */
    use HasFactory;

    /** @return BelongsTo<LoyaltyMember, $this> */
    public function loyaltyMember(): BelongsTo
    {
        return $this->belongsTo(LoyaltyMember::class);
    }

    /** @return BelongsTo<OperationalCustomer, $this> */
    public function operationalCustomer(): BelongsTo
    {
        return $this->belongsTo(OperationalCustomer::class);
    }
}
