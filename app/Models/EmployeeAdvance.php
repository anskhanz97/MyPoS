<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $id @property string $public_id @property string $outstanding_amount */
#[Fillable(['merchant_id', 'staff_profile_id', 'original_amount', 'outstanding_amount', 'reason', 'issued_on', 'state', 'created_by_user_id'])]
class EmployeeAdvance extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2', 'outstanding_amount' => 'decimal:2', 'issued_on' => 'immutable_date'];
    }
}
