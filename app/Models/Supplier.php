<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property string $code
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $tax_reference
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $notes
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable(['merchant_id', 'code', 'name', 'contact_name', 'phone', 'email', 'tax_reference', 'address_line_1', 'address_line_2', 'city', 'notes', 'archived_at'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }
}
