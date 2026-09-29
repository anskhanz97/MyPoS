<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'payroll_item_id', 'kind', 'amount', 'provenance', 'label', 'reason', 'created_by_user_id'])]
class PayrollAdjustment extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
