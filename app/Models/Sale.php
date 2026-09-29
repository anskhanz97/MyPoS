<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property string $state
 * @property string $source
 * @property string $client_transaction_id
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property string $pricing_version
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property int $cash_session_id
 * @property string $subtotal
 * @property string $discount_total
 * @property string $net_total
 * @property string $tax_total
 * @property string $total
 * @property string $cash_change
 * @property array<string, mixed>|null $customer_snapshot
 * @property CarbonImmutable $business_date
 * @property CarbonImmutable $finalized_at
 * @property-read Collection<int, SaleLine> $lines
 * @property-read Collection<int, SaleTender> $tenders
 * @property-read ReceiptSnapshot $receipt
 * @property-read Register $register
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'cash_session_id', 'cart_id', 'cart_quote_id', 'customer_id', 'created_by_user_id', 'number', 'state', 'source', 'client_transaction_id', 'idempotency_key', 'payload_hash', 'pricing_version', 'cart_version', 'quote_version', 'business_date', 'currency', 'subtotal', 'discount_total', 'net_total', 'tax_total', 'total', 'cash_change', 'customer_snapshot', 'finalized_at'])]
class Sale extends Model
{
    use HasBinaryPublicId;

    /** @return HasMany<SaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class)->orderBy('sequence');
    }

    /** @return HasMany<SaleTender, $this> */
    public function tenders(): HasMany
    {
        return $this->hasMany(SaleTender::class);
    }

    /** @return HasOne<ReceiptSnapshot, $this> */
    public function receipt(): HasOne
    {
        return $this->hasOne(ReceiptSnapshot::class);
    }

    /** @return HasMany<SaleReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /** @return BelongsTo<Register, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    protected function casts(): array
    {
        return ['cart_version' => 'integer', 'quote_version' => 'integer', 'business_date' => 'immutable_date', 'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'net_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'cash_change' => 'decimal:2', 'customer_snapshot' => 'array', 'finalized_at' => 'immutable_datetime'];
    }
}
