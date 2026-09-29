<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $staff_profile_id
 * @property string $state
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $version
 */
#[Fillable(['merchant_id', 'store_id', 'staff_profile_id', 'created_by_user_id', 'state', 'starts_at', 'ends_at', 'notes', 'version'])]
class RotaShift extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
