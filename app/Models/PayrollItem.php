<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $payroll_run_id
 * @property int $staff_profile_id
 * @property string $state
 * @property string $base_amount
 * @property string $overtime_amount
 * @property string $allowance_amount
 * @property string $deduction_amount
 * @property string $statutory_amount
 * @property string $advance_amount
 * @property string $gross_amount
 * @property string $net_amount
 * @property string $paid_amount
 * @property array<string, mixed> $calculation
 * @property int $version
 */
#[Fillable(['merchant_id', 'payroll_run_id', 'staff_profile_id', 'payroll_input_snapshot_id', 'state', 'base_amount', 'overtime_amount', 'allowance_amount', 'deduction_amount', 'statutory_amount', 'advance_amount', 'gross_amount', 'net_amount', 'paid_amount', 'calculation', 'version'])]
class PayrollItem extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /** @return HasMany<PayrollAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    /** @return HasMany<PayrollPayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(PayrollPayout::class);
    }

    protected function casts(): array
    {
        return ['base_amount' => 'decimal:2', 'overtime_amount' => 'decimal:2', 'allowance_amount' => 'decimal:2', 'deduction_amount' => 'decimal:2', 'statutory_amount' => 'decimal:2', 'advance_amount' => 'decimal:2', 'gross_amount' => 'decimal:2', 'net_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'calculation' => 'array', 'version' => 'integer'];
    }
}
