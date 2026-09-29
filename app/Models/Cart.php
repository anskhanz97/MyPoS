<?php

namespace App\Models;

use App\Domain\Checkout\Enums\CartStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property int $inventory_location_id
 * @property int|null $customer_id
 * @property string $reference
 * @property CartStatus $status
 * @property int $version
 * @property string|null $pricing_version
 * @property array<int, string>|null $coupon_codes
 * @property string $manual_cart_discount
 * @property CarbonImmutable|null $suspended_at
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'inventory_location_id', 'customer_id', 'created_by_user_id', 'last_edited_by_user_id', 'reference', 'status', 'version', 'pricing_version', 'coupon_codes', 'manual_cart_discount', 'suspended_at', 'resumed_at'])]
class Cart extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<Register, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /** @return BelongsTo<OperationalCustomer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(OperationalCustomer::class, 'customer_id');
    }

    /** @return HasMany<CartLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CartLine::class)->orderBy('sequence');
    }

    /** @return HasMany<CartQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(CartQuote::class);
    }

    protected function casts(): array
    {
        return ['status' => CartStatus::class, 'version' => 'integer', 'coupon_codes' => 'array', 'manual_cart_discount' => 'decimal:2', 'suspended_at' => 'immutable_datetime', 'resumed_at' => 'immutable_datetime'];
    }
}
