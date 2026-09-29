<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $allocated_days
 * @property string $used_days
 * @property string $reserved_days
 * @property int $version
 */
#[Fillable(['merchant_id', 'staff_profile_id', 'leave_type_id', 'year', 'allocated_days', 'used_days', 'reserved_days', 'version'])]
class LeaveBalance extends Model
{
    protected function casts(): array
    {
        return ['year' => 'integer', 'allocated_days' => 'decimal:2', 'used_days' => 'decimal:2', 'reserved_days' => 'decimal:2', 'version' => 'integer'];
    }
}
