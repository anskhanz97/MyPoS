<?php

namespace App\Models;

use App\Domain\Inventory\Enums\AdjustmentDirection;
use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\InventoryAdjustmentReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property string $code
 * @property string $name
 * @property AdjustmentDirection $direction
 * @property bool $requires_approval
 * @property bool $is_active
 */
#[Fillable(['merchant_id', 'code', 'name', 'direction', 'requires_approval', 'is_active'])]
class InventoryAdjustmentReason extends Model
{
    /** @use HasFactory<InventoryAdjustmentReasonFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['direction' => AdjustmentDirection::class, 'requires_approval' => 'boolean', 'is_active' => 'boolean'];
    }
}
