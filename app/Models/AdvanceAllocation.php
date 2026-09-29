<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'employee_advance_id', 'payroll_item_id', 'amount', 'allocated_at'])]
class AdvanceAllocation extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'allocated_at' => 'immutable_datetime'];
    }
}
