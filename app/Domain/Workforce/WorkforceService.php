<?php

namespace App\Domain\Workforce;

use App\Models\AttendanceEvent;
use App\Models\AttendanceShift;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Merchant;
use App\Models\PayrollInputSnapshot;
use App\Models\RotaShift;
use App\Models\SecurityAuditEvent;
use App\Models\StaffProfile;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkforcePolicy;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class WorkforceService
{
    /** @return array{shift:AttendanceShift,event:AttendanceEvent,replayed:bool} */
    public function clock(Merchant $merchant, Store $store, StaffProfile $staff, User $actor, string $key, string $action, CarbonImmutable $occurredAt): array
    {
        $hash = hash('sha256', json_encode([$staff->public_id, $store->public_id, $action, $occurredAt->toAtomString()], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $store, $staff, $actor, $key, $action, $occurredAt, $hash): array {
            abort_unless($staff->merchant_id === $merchant->id && $store->merchant_id === $merchant->id, 404);
            $existing = AttendanceEvent::query()->where('merchant_id', $merchant->id)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing instanceof AttendanceEvent) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The clock identity was reused with different data.');
                }

                return ['shift' => AttendanceShift::query()->findOrFail($existing->attendance_shift_id), 'event' => $existing, 'replayed' => true];
            }
            StaffProfile::query()->lockForUpdate()->findOrFail($staff->id);
            $shift = AttendanceShift::query()->where('staff_profile_id', $staff->id)->whereIn('state', ['open', 'on_break'])->lockForUpdate()->first();
            if ($action === 'clock_in') {
                abort_if($shift instanceof AttendanceShift, 409, 'This staff member is already clocked in.');
                $rota = RotaShift::query()->where('staff_profile_id', $staff->id)->where('state', 'planned')->where('starts_at', '<=', $occurredAt->addHours(4))->where('ends_at', '>=', $occurredAt->subHours(4))->orderBy('starts_at')->first();
                $shift = AttendanceShift::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'staff_profile_id' => $staff->id, 'rota_shift_id' => $rota?->id, 'state' => 'open', 'clocked_in_at' => $occurredAt, 'break_seconds' => 0]);
            } elseif ($action === 'break_start') {
                abort_unless($shift instanceof AttendanceShift && $shift->state === 'open', 409, 'Clock in before starting a break.');
                $shift->forceFill(['state' => 'on_break', 'open_break_at' => $occurredAt])->save();
            } elseif ($action === 'break_end') {
                abort_unless($shift instanceof AttendanceShift && $shift->state === 'on_break' && $shift->open_break_at !== null, 409, 'No open break exists.');
                abort_if($occurredAt->lessThan($shift->open_break_at), 422, 'Break end cannot precede break start.');
                $shift->forceFill(['state' => 'open', 'break_seconds' => $shift->break_seconds + $shift->open_break_at->diffInSeconds($occurredAt), 'open_break_at' => null])->save();
            } else {
                abort_unless($action === 'clock_out' && $shift instanceof AttendanceShift, 409, 'No open attendance shift exists.');
                abort_if($occurredAt->lessThanOrEqualTo($shift->clocked_in_at), 422, 'Clock-out must follow clock-in.');
                $break = $shift->break_seconds;
                if ($shift->open_break_at !== null) {
                    $break += $shift->open_break_at->diffInSeconds($occurredAt);
                }
                $policy = WorkforcePolicy::query()->firstOrCreate(['merchant_id' => $merchant->id], ['grace_minutes' => 5, 'overtime_after_minutes' => 480, 'max_shift_minutes' => 960]);
                $minutes = $shift->clocked_in_at->diffInMinutes($occurredAt);
                $shift->forceFill(['state' => $minutes > $policy->max_shift_minutes ? 'needs_review' : 'complete', 'clocked_out_at' => $occurredAt, 'break_seconds' => $break, 'open_break_at' => null])->save();
            }
            $event = AttendanceEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'staff_profile_id' => $staff->id, 'attendance_shift_id' => $shift->id, 'actor_user_id' => $actor->id, 'idempotency_key' => $key, 'payload_hash' => $hash, 'event_type' => $action, 'occurred_at' => $occurredAt]);

            return ['shift' => $shift->refresh(), 'event' => $event, 'replayed' => false];
        }, attempts: 3);
    }

    public function createRota(Merchant $merchant, Store $store, StaffProfile $staff, User $actor, CarbonImmutable $starts, CarbonImmutable $ends, ?string $notes): RotaShift
    {
        abort_unless($staff->merchant_id === $merchant->id && $store->merchant_id === $merchant->id, 404);
        abort_unless($ends->greaterThan($starts) && $starts->diffInMinutes($ends) <= 1440, 422, 'A rota shift must end after it starts and be no longer than 24 hours.');

        return DB::transaction(function () use ($merchant, $store, $staff, $actor, $starts, $ends, $notes): RotaShift {
            StaffProfile::query()->lockForUpdate()->findOrFail($staff->id);
            $overlap = RotaShift::query()->where('staff_profile_id', $staff->id)->where('state', 'planned')->where('starts_at', '<', $ends)->where('ends_at', '>', $starts)->lockForUpdate()->exists();
            abort_if($overlap, 409, 'This rota shift overlaps another assignment.');
            $leave = LeaveRequest::query()->where('staff_profile_id', $staff->id)->where('state', 'approved')->whereDate('starts_on', '<=', $ends->toDateString())->whereDate('ends_on', '>=', $starts->toDateString())->exists();
            abort_if($leave, 409, 'This rota shift conflicts with approved leave.');

            return RotaShift::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'staff_profile_id' => $staff->id, 'created_by_user_id' => $actor->id, 'state' => 'planned', 'starts_at' => $starts, 'ends_at' => $ends, 'notes' => $notes, 'version' => 1]);
        });
    }

    public function correct(AttendanceShift $shift, User $actor, string $key, CarbonImmutable $clockIn, CarbonImmutable $clockOut, int $breakSeconds, string $reason): AttendanceShift
    {
        abort_unless($clockOut->greaterThan($clockIn), 422, 'Corrected clock-out must follow clock-in.');

        return DB::transaction(function () use ($shift, $actor, $key, $clockIn, $clockOut, $breakSeconds, $reason): AttendanceShift {
            $after = ['clocked_in_at' => $clockIn->toAtomString(), 'clocked_out_at' => $clockOut->toAtomString(), 'break_seconds' => $breakSeconds, 'state' => 'corrected'];
            $payloadHash = hash('sha256', json_encode($after, JSON_THROW_ON_ERROR));
            $existing = AttendanceEvent::query()->where('merchant_id', $shift->merchant_id)->where('idempotency_key', $key)->first();
            if ($existing instanceof AttendanceEvent) {
                abort_unless(hash_equals($existing->payload_hash, $payloadHash), 409, 'The correction identity was reused with different data.');

                return AttendanceShift::query()->findOrFail($existing->attendance_shift_id);
            }
            $locked = AttendanceShift::query()->lockForUpdate()->findOrFail($shift->id);
            $before = ['clocked_in_at' => $locked->clocked_in_at->toAtomString(), 'clocked_out_at' => $locked->clocked_out_at?->toAtomString(), 'break_seconds' => $locked->break_seconds, 'state' => $locked->state];
            $locked->forceFill(['clocked_in_at' => $clockIn, 'clocked_out_at' => $clockOut, 'break_seconds' => $breakSeconds, 'open_break_at' => null, 'state' => 'corrected'])->save();
            AttendanceEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'staff_profile_id' => $locked->staff_profile_id, 'attendance_shift_id' => $locked->id, 'actor_user_id' => $actor->id, 'idempotency_key' => $key, 'payload_hash' => $payloadHash, 'event_type' => 'correction', 'occurred_at' => now(), 'reason' => $reason, 'before_values' => $before, 'after_values' => $after]);
            $this->audit($locked->merchant_id, $locked->store_id, $actor, 'workforce.attendance.corrected', ['shift_id' => $locked->public_id, 'reason' => $reason, 'before' => $before, 'after' => $after]);

            return $locked->refresh();
        });
    }

    public function requestLeave(Merchant $merchant, StaffProfile $staff, LeaveType $type, CarbonImmutable $start, CarbonImmutable $end, ?string $note): LeaveRequest
    {
        abort_unless($staff->merchant_id === $merchant->id && $type->merchant_id === $merchant->id && $type->is_active, 404);
        abort_unless($end->greaterThanOrEqualTo($start), 422, 'Leave end must not precede start.');

        return DB::transaction(function () use ($merchant, $staff, $type, $start, $end, $note): LeaveRequest {
            StaffProfile::query()->lockForUpdate()->findOrFail($staff->id);
            abort_if(LeaveRequest::query()->where('staff_profile_id', $staff->id)->whereIn('state', ['pending', 'approved'])->where('starts_on', '<=', $end->toDateString())->where('ends_on', '>=', $start->toDateString())->exists(), 409, 'This leave request overlaps existing leave.');
            $days = $start->diffInDays($end) + 1;

            return LeaveRequest::query()->create(['merchant_id' => $merchant->id, 'staff_profile_id' => $staff->id, 'leave_type_id' => $type->id, 'state' => 'pending', 'starts_on' => $start, 'ends_on' => $end, 'days' => (string) $days, 'staff_note' => $note, 'version' => 1]);
        });
    }

    public function decideLeave(LeaveRequest $request, User $actor, string $decision, string $reason): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor, $decision, $reason): LeaveRequest {
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($locked->state === 'pending', 409, 'Only pending leave can be decided.');
            $balance = LeaveBalance::query()->firstOrCreate(['merchant_id' => $locked->merchant_id, 'staff_profile_id' => $locked->staff_profile_id, 'leave_type_id' => $locked->leave_type_id, 'year' => $locked->starts_on->year], ['allocated_days' => '0.00', 'used_days' => '0.00', 'reserved_days' => '0.00', 'version' => 1]);
            $balance = LeaveBalance::query()->lockForUpdate()->findOrFail($balance->id);
            if ($decision === 'approved') {
                $available = BigDecimal::of($balance->allocated_days)->minus($balance->used_days);
                abort_if($available->isLessThan($locked->days), 409, 'The leave balance is insufficient.');
                $balance->forceFill(['used_days' => (string) BigDecimal::of($balance->used_days)->plus($locked->days), 'version' => $balance->version + 1])->save();
            }
            abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
            $locked->forceFill(['state' => $decision, 'decided_by_user_id' => $actor->id, 'decision_reason' => $reason, 'decided_at' => now(), 'version' => $locked->version + 1])->save();
            $this->audit($locked->merchant_id, null, $actor, 'workforce.leave.decided', ['leave_request_id' => $locked->public_id, 'decision' => $decision, 'reason' => $reason]);

            return $locked->refresh();
        });
    }

    public function cancelLeave(LeaveRequest $request, User $actor, string $reason): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason): LeaveRequest {
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless(in_array($locked->state, ['pending', 'approved'], true), 409);
            if ($locked->state === 'approved') {
                $balance = LeaveBalance::query()->where('staff_profile_id', $locked->staff_profile_id)->where('leave_type_id', $locked->leave_type_id)->where('year', $locked->starts_on->year)->lockForUpdate()->firstOrFail();
                $remaining = BigDecimal::of($balance->used_days)->minus($locked->days);
                $balance->forceFill(['used_days' => (string) ($remaining->isNegative() ? BigDecimal::zero() : $remaining), 'version' => $balance->version + 1])->save();
            }
            $locked->forceFill(['state' => 'cancelled', 'decided_by_user_id' => $actor->id, 'decision_reason' => $reason, 'decided_at' => now(), 'version' => $locked->version + 1])->save();
            $this->audit($locked->merchant_id, null, $actor, 'workforce.leave.cancelled', ['leave_request_id' => $locked->public_id, 'reason' => $reason]);

            return $locked->refresh();
        });
    }

    public function freeze(StaffProfile $staff, User $actor, CarbonImmutable $start, CarbonImmutable $end): PayrollInputSnapshot
    {
        abort_unless($end->greaterThanOrEqualTo($start), 422, 'Payroll period end must not precede its start.');

        return DB::transaction(function () use ($staff, $actor, $start, $end): PayrollInputSnapshot {
            $existing = PayrollInputSnapshot::query()->where('staff_profile_id', $staff->id)->where('period_start', $start->toDateString())->where('period_end', $end->toDateString())->first();
            if ($existing instanceof PayrollInputSnapshot) {
                return $existing;
            }
            $policy = WorkforcePolicy::query()->firstOrCreate(['merchant_id' => $staff->merchant_id], ['grace_minutes' => 5, 'overtime_after_minutes' => 480, 'max_shift_minutes' => 960]);
            $shifts = AttendanceShift::query()->where('staff_profile_id', $staff->id)->whereNotNull('clocked_out_at')->whereBetween('clocked_in_at', [$start->startOfDay(), $end->endOfDay()])->get();
            $minutes = 0;
            $overtime = 0;
            foreach ($shifts as $shift) {
                $worked = max(0, $shift->clocked_in_at->diffInMinutes($shift->clocked_out_at) - intdiv($shift->break_seconds, 60));
                $minutes += $worked;
                $overtime += max(0, $worked - $policy->overtime_after_minutes);
            }
            $leave = LeaveRequest::query()->where('staff_profile_id', $staff->id)->where('state', 'approved')->where('starts_on', '<=', $end->toDateString())->where('ends_on', '>=', $start->toDateString())->sum('days');
            $unpaidLeave = LeaveRequest::query()->where('staff_profile_id', $staff->id)->where('state', 'approved')->where('starts_on', '<=', $end->toDateString())->where('ends_on', '>=', $start->toDateString())->whereHas('leaveType', fn ($query) => $query->where('is_paid', false))->sum('days');
            $payload = ['staff_id' => $staff->public_id, 'employee_number' => $staff->employee_number, 'pay_basis' => $staff->pay_basis, 'base_pay' => $staff->base_pay, 'overtime_eligible' => $staff->overtime_eligible, 'worked_minutes' => $minutes, 'overtime_minutes' => $staff->overtime_eligible ? $overtime : 0, 'approved_leave_days' => (string) $leave, 'unpaid_leave_days' => (string) $unpaidLeave, 'attendance_shift_ids' => $shifts->pluck('public_id')->all()];
            $json = json_encode($payload, JSON_THROW_ON_ERROR);

            return PayrollInputSnapshot::query()->create(['merchant_id' => $staff->merchant_id, 'staff_profile_id' => $staff->id, 'frozen_by_user_id' => $actor->id, 'period_start' => $start, 'period_end' => $end, 'content_hash' => hash('sha256', $json), 'payload' => $payload, 'frozen_at' => now()]);
        });
    }

    /** @param array<string, mixed> $metadata */
    private function audit(int $merchantId, ?int $storeId, User $actor, string $code, array $metadata): void
    {
        SecurityAuditEvent::query()->create(['merchant_id' => $merchantId, 'store_id' => $storeId, 'actor_user_id' => $actor->id, 'event_code' => $code, 'outcome' => 'success', 'metadata' => $metadata, 'occurred_at' => now()]);
    }
}
