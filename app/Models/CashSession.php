<?php

namespace App\Models;

use App\Domain\Cash\Enums\CashSessionStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property int|null $current_custodian_user_id
 * @property CashSessionStatus $status
 * @property string $opening_amount
 * @property string|null $expected_amount
 * @property string|null $counted_amount
 * @property string|null $variance_amount
 * @property CarbonImmutable $business_date
 * @property int $version
 * @property int $handover_count
 * @property int $pending_server_sale_count
 * @property int $pending_offline_sale_count
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $close_cutoff_at
 * @property CarbonImmutable|null $closed_at
 * @property-read Register $register
 * @property-read Collection<int, CashMovement> $movements
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'opened_by_user_id', 'current_custodian_user_id', 'closed_by_user_id', 'number', 'status', 'active_guard', 'open_idempotency_key', 'open_payload_hash', 'currency', 'business_date', 'opening_amount', 'expected_amount', 'counted_amount', 'variance_amount', 'version', 'handover_count', 'pending_server_sale_count', 'pending_offline_sale_count', 'opened_at', 'close_cutoff_at', 'count_submitted_at', 'closed_at'])]
class CashSession extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<Register, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** @return HasMany<CashSessionCount, $this> */
    public function counts(): HasMany
    {
        return $this->hasMany(CashSessionCount::class);
    }

    /** @return HasMany<CashSessionHandover, $this> */
    public function handovers(): HasMany
    {
        return $this->hasMany(CashSessionHandover::class);
    }

    /** @return HasMany<CashVarianceReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(CashVarianceReview::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class, 'business_date' => 'immutable_date',
            'opening_amount' => 'decimal:2', 'expected_amount' => 'decimal:2', 'counted_amount' => 'decimal:2', 'variance_amount' => 'decimal:2',
            'version' => 'integer', 'handover_count' => 'integer', 'pending_server_sale_count' => 'integer', 'pending_offline_sale_count' => 'integer',
            'opened_at' => 'immutable_datetime', 'close_cutoff_at' => 'immutable_datetime', 'count_submitted_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime',
        ];
    }
}
