<?php

namespace App\Models;

use App\Domain\Catalog\Enums\ScaleValueType;
use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\ScaleBarcodeFormatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property ScaleValueType $value_type */
#[Fillable(['merchant_id', 'store_id', 'name', 'prefix', 'total_length', 'item_start', 'item_length', 'value_start', 'value_length', 'value_type', 'value_decimal_places', 'checksum', 'is_active'])]
class ScaleBarcodeFormat extends Model
{
    /** @use HasFactory<ScaleBarcodeFormatFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['total_length' => 'integer', 'item_start' => 'integer', 'item_length' => 'integer', 'value_start' => 'integer', 'value_length' => 'integer', 'value_type' => ScaleValueType::class, 'value_decimal_places' => 'integer', 'is_active' => 'boolean'];
    }
}
