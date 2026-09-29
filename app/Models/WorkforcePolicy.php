<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $merchant_id @property int $grace_minutes @property int $max_shift_minutes @property int $break_required_after_minutes @property int $overtime_after_minutes @property int $version */
#[Fillable(['merchant_id', 'grace_minutes', 'max_shift_minutes', 'break_required_after_minutes', 'overtime_after_minutes', 'version'])]
class WorkforcePolicy extends Model
{
    protected function casts(): array
    {
        return ['grace_minutes' => 'integer', 'max_shift_minutes' => 'integer', 'break_required_after_minutes' => 'integer', 'overtime_after_minutes' => 'integer', 'version' => 'integer'];
    }
}
