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
 * @property CarbonImmutable $clocked_in_at
 * @property CarbonImmutable|null $clocked_out_at
 * @property int $break_seconds
 * @property CarbonImmutable|null $open_break_at
 * @property StaffProfile $staffProfile
 */
#[Fillable(['merchant_id', 'store_id', 'staff_profile_id', 'rota_shift_id', 'state', 'clocked_in_at', 'clocked_out_at', 'break_seconds', 'open_break_at'])]
class AttendanceShift extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    protected function casts(): array
    {
        return ['clocked_in_at' => 'immutable_datetime', 'clocked_out_at' => 'immutable_datetime', 'open_break_at' => 'immutable_datetime', 'break_seconds' => 'integer'];
    }
}
