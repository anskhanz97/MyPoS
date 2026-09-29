<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\UnitConversionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $factor_to_base */
#[Fillable(['merchant_id', 'product_variant_id', 'from_unit_id', 'factor_to_base'])]
class UnitConversion extends Model
{
    /** @use HasFactory<UnitConversionFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'from_unit_id');
    }

    protected function casts(): array
    {
        return ['factor_to_base' => 'decimal:6'];
    }
}
