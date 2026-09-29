<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property string $pay_basis @property string $base_rate @property string $standard_monthly_hours @property string $overtime_multiplier */
#[Fillable(['merchant_id', 'staff_profile_id', 'effective_from', 'effective_until', 'pay_basis', 'base_rate', 'standard_monthly_hours', 'overtime_multiplier', 'created_by_user_id'])]
class StaffPayTerm extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_until' => 'immutable_date', 'base_rate' => 'decimal:2', 'standard_monthly_hours' => 'decimal:2', 'overtime_multiplier' => 'decimal:4'];
    }
}
