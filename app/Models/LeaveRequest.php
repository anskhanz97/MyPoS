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
 * @property int $staff_profile_id
 * @property int $leave_type_id
 * @property string $state
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property string $days
 * @property int $version
 */
#[Fillable(['merchant_id', 'staff_profile_id', 'leave_type_id', 'decided_by_user_id', 'state', 'starts_on', 'ends_on', 'days', 'staff_note', 'decision_reason', 'decided_at', 'version'])]
class LeaveRequest extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'days' => 'decimal:2', 'decided_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
