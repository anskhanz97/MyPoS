<?php

namespace App\Models;

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
 * @property int $user_id
 * @property int|null $default_store_id
 * @property string $employee_number
 * @property string $display_name
 * @property string $employment_status
 * @property CarbonImmutable $hire_date
 * @property string $pay_basis
 * @property string $base_pay
 * @property bool $overtime_eligible
 * @property int $version
 */
#[Fillable(['merchant_id', 'user_id', 'default_store_id', 'employee_number', 'display_name', 'employment_status', 'hire_date', 'end_date', 'pay_basis', 'base_pay', 'overtime_eligible', 'version'])]
class StaffProfile extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RotaShift, $this> */
    public function rotaShifts(): HasMany
    {
        return $this->hasMany(RotaShift::class);
    }

    /** @return HasMany<AttendanceShift, $this> */
    public function attendanceShifts(): HasMany
    {
        return $this->hasMany(AttendanceShift::class);
    }

    protected function casts(): array
    {
        return ['hire_date' => 'immutable_date', 'end_date' => 'immutable_date', 'base_pay' => 'decimal:2', 'overtime_eligible' => 'boolean', 'version' => 'integer'];
    }
}
