<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\OperationalCustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int|null $store_id
 * @property int|null $customer_group_id
 * @property string $local_reference
 * @property string $name
 * @property string|null $phone
 * @property string|null $normalized_phone
 * @property string|null $email
 * @property string|null $normalized_email
 * @property bool $email_receipt_preference
 * @property bool $marketing_email_consent
 * @property CarbonImmutable|null $marketing_consent_at
 * @property string|null $marketing_consent_source
 * @property CarbonImmutable|null $archived_at
 * @property int $version
 */
#[Fillable(['merchant_id', 'store_id', 'customer_group_id', 'local_reference', 'name', 'phone', 'normalized_phone', 'email', 'normalized_email', 'address_line', 'city', 'postal_code', 'email_receipt_preference', 'marketing_email_consent', 'marketing_consent_at', 'marketing_consent_source', 'archived_at', 'version'])]
class OperationalCustomer extends Model
{
    /** @use HasFactory<OperationalCustomerFactory> */
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

    /** @return BelongsTo<CustomerGroup, $this> */
    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /** @return HasOne<LoyaltyCustomerLink, $this> */
    public function loyaltyLink(): HasOne
    {
        return $this->hasOne(LoyaltyCustomerLink::class);
    }

    protected function casts(): array
    {
        return ['email_receipt_preference' => 'boolean', 'marketing_email_consent' => 'boolean', 'marketing_consent_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
