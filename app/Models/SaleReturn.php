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
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property int $cash_session_id
 * @property int $sale_id
 * @property int|null $approval_id
 * @property string $number
 * @property string $state
 * @property string $payload_hash
 * @property string $reason
 * @property string|null $late_reason
 * @property int $policy_window_days
 * @property bool $late_exception
 * @property CarbonImmutable $business_date
 * @property string $subtotal
 * @property string $discount_total
 * @property string $net_total
 * @property string $tax_total
 * @property string $total
 * @property array<string,mixed> $receipt_payload
 * @property CarbonImmutable $posted_at
 * @property-read Sale $sale
 * @property-read Register $register
 * @property-read CashSession $cashSession
 * @property-read Approval|null $approval
 * @property-read Collection<int,SaleReturnLine> $lines
 * @property-read Collection<int,SaleReturnTender> $tenders
 * @property-read SaleExchange|null $exchange
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'cash_session_id', 'sale_id', 'created_by_user_id', 'approval_id', 'number', 'state', 'idempotency_key', 'payload_hash', 'reason', 'late_reason', 'policy_window_days', 'late_exception', 'business_date', 'currency', 'subtotal', 'discount_total', 'net_total', 'tax_total', 'total', 'receipt_payload', 'posted_at'])]
class SaleReturn extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Register, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /** @return BelongsTo<Approval, $this> */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    /** @return HasMany<SaleReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class)->orderBy('sequence');
    }

    /** @return HasMany<SaleReturnTender, $this> */
    public function tenders(): HasMany
    {
        return $this->hasMany(SaleReturnTender::class);
    }

    /** @return HasOne<SaleExchange, $this> */
    public function exchange(): HasOne
    {
        return $this->hasOne(SaleExchange::class);
    }

    protected function casts(): array
    {
        return ['late_exception' => 'boolean', 'policy_window_days' => 'integer', 'business_date' => 'immutable_date', 'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'net_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'receipt_payload' => 'array', 'posted_at' => 'immutable_datetime'];
    }
}
