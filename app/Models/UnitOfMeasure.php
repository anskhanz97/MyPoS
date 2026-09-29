<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'code', 'name', 'symbol', 'decimal_places', 'is_active'])]
class UnitOfMeasure extends Model
{
    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['decimal_places' => 'integer', 'is_active' => 'boolean'];
    }
}
