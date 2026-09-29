<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $id @property string $points */
#[Fillable(['merchant_id', 'loyalty_member_id', 'debit_entry_id', 'earn_entry_id', 'points'])]
class LoyaltyPointConsumption extends Model
{
    protected function casts(): array
    {
        return ['points' => 'decimal:4'];
    }
}
