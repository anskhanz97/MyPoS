<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $loyalty_program_id
 * @property string $name
 * @property string $points_required
 * @property string $discount_amount
 * @property bool $is_active
 * @property int $version
 */
#[Fillable(['merchant_id', 'loyalty_program_id', 'name', 'points_required', 'discount_amount', 'is_active', 'version'])]
class LoyaltyReward extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['points_required' => 'decimal:4', 'discount_amount' => 'decimal:2', 'is_active' => 'boolean', 'version' => 'integer'];
    }
}
