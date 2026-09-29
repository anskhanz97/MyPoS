<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $merchant_id
 * @property int $loyalty_member_id
 * @property string $balance
 * @property int $version
 */
#[Fillable(['merchant_id', 'loyalty_member_id', 'balance', 'version'])]
class LoyaltyPointBalance extends Model
{
    protected function casts(): array
    {
        return ['balance' => 'decimal:4', 'version' => 'integer'];
    }
}
