<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'code', 'name', 'is_active'])] class ExpenseCategory extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
