<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $loyalty_program_id
 * @property int $version
 * @property string $points_per_pkr
 * @property int|null $expiry_days
 * @property CarbonImmutable $effective_at
 */
#[Fillable(['merchant_id', 'loyalty_program_id', 'version', 'points_per_pkr', 'expiry_days', 'published_by_user_id', 'effective_at'])]
class LoyaltyProgramVersion extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['version' => 'integer', 'points_per_pkr' => 'decimal:8', 'expiry_days' => 'integer', 'effective_at' => 'immutable_datetime'];
    }
}
