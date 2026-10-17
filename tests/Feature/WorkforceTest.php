<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendanceShift;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\PayrollInputSnapshot;
use App\Models\SecurityAuditEvent;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class WorkforceTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_clock_is_retry_safe_supports_breaks_and_overnight_work(): void
    {
        $fixture = $this->pricingFixture();
        $staff = $this->staff($fixture, 'EMP-001', $fixture['cashier']->id);
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/attendance/clock";
        $key = (string) Str::uuid();
        $clockIn = ['action' => 'clock_in', 'occurred_at' => '2026-09-28T17:00:00Z'];

        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($base, $clockIn)->assertCreated()->assertJsonPath('data.state', 'open');
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($base, $clockIn)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($base, ['action' => 'clock_out', 'occurred_at' => '2026-09-29T01:00:00Z'])->assertConflict();
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base, ['action' => 'break_start', 'occurred_at' => '2026-09-28T20:00:00Z'])->assertCreated();
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base, ['action' => 'break_end', 'occurred_at' => '2026-09-28T20:30:00Z'])->assertCreated();
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base, ['action' => 'clock_out', 'occurred_at' => '2026-09-29T01:00:00Z'])->assertCreated()->assertJsonPath('data.state', 'complete');

        $shift = AttendanceShift::query()->firstOrFail();
        $this->assertSame(1800, $shift->break_seconds);
        $this->assertSame(4, AttendanceEvent::query()->count());
        $this->assertSame($staff->id, $shift->staff_profile_id);
        $reports = '/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/';
        $range = '?start=2026-09-28&end=2026-09-29&store_id='.$fixture['store']->public_id;
        $this->actingAs($fixture['manager'])->getJson($reports.'clock-in-out'.$range)->assertOk()->assertJsonPath('data.rows.0.worked_hours', fn ($hours) => (float) $hours === 7.5);
        $this->getJson($reports.'attendance-logs'.$range)->assertOk()->assertJsonPath('data.pagination.total', 4);
    }

    public function test_leave_conflicts_balance_reversal_and_salary_privacy(): void
    {
        $fixture = $this->pricingFixture();
        $staff = $this->staff($fixture, 'EMP-002', $fixture['cashier']->id);
        $type = LeaveType::query()->create(['merchant_id' => $fixture['merchant']->id, 'code' => 'ANNUAL', 'name' => 'Annual leave', 'is_paid' => true, 'is_active' => true]);
        LeaveBalance::query()->create(['merchant_id' => $fixture['merchant']->id, 'staff_profile_id' => $staff->id, 'leave_type_id' => $type->id, 'year' => 2026, 'allocated_days' => '10.00', 'used_days' => '0.00', 'reserved_days' => '0.00', 'version' => 1]);
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}";
        $payload = ['leave_type_id' => $type->public_id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'note' => 'Family commitment'];
        $leave = $this->actingAs($fixture['cashier'])->postJson($base.'/leave-requests', $payload)->assertCreated()->json('data');
        $this->actingAs($fixture['cashier'])->postJson($base.'/leave-requests', $payload)->assertConflict();
        $this->actingAs($fixture['manager'])->postJson($base.'/leave-requests/'.$leave['public_id'].'/decision', ['decision' => 'approved', 'reason' => 'Coverage arranged'])->assertOk();
        $this->assertSame('3.00', LeaveBalance::query()->firstOrFail()->used_days);
        $this->getJson($base.'/reporting/reports/leave?start=2026-10-01&end=2026-10-03&store_id='.$fixture['store']->public_id)->assertOk()->assertJsonPath('data.rows.0.state', 'approved');
        $this->actingAs($fixture['cashier'])->postJson($base.'/leave-requests/'.$leave['public_id'].'/cancel', ['reason' => 'Plans changed'])->assertOk()->assertJsonPath('data.state', 'cancelled');
        $this->assertSame('0.00', LeaveBalance::query()->firstOrFail()->used_days);
        $this->assertSame(2, SecurityAuditEvent::query()->where('event_code', 'like', 'workforce.leave.%')->count());

        $this->actingAs($fixture['manager'])->getJson($base.'/staff')->assertOk()->assertJsonMissingPath('data.0.base_pay');
        $this->actingAs($fixture['owner'])->getJson($base.'/staff')->assertOk()->assertJsonPath('data.0.base_pay', '75000.00');
        $this->actingAs($fixture['manager'])->postJson($base.'/staff/'.$staff->public_id.'/payroll-input-snapshots', ['period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertForbidden();
    }

    public function test_rota_overlap_attendance_correction_export_scope_and_frozen_input_immutability(): void
    {
        $fixture = $this->pricingFixture();
        $staff = $this->staff($fixture, 'EMP-003', $fixture['cashier']->id);
        $merchant = $fixture['merchant']->public_id;
        $store = $fixture['store']->public_id;
        $rota = "/api/v1/merchants/{$merchant}/stores/{$store}/rota-shifts";
        $payload = ['staff_id' => $staff->public_id, 'starts_at' => '2026-09-10T09:00:00+05:00', 'ends_at' => '2026-09-10T17:00:00+05:00', 'notes' => 'Front counter'];
        $this->actingAs($fixture['manager'])->postJson($rota, $payload)->assertCreated();
        $this->actingAs($fixture['manager'])->postJson($rota, $payload)->assertConflict();

        $shift = AttendanceShift::query()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'staff_profile_id' => $staff->id, 'state' => 'needs_review', 'clocked_in_at' => '2026-09-10 09:10:00', 'break_seconds' => 0]);
        $correctionUrl = "/api/v1/merchants/{$merchant}/stores/{$store}/attendance/{$shift->public_id}/corrections";
        $correction = ['clocked_in_at' => '2026-09-10T09:00:00+05:00', 'clocked_out_at' => '2026-09-10T17:00:00+05:00', 'break_seconds' => 1800, 'reason' => 'Missed clock out confirmed by manager'];
        $key = (string) Str::uuid();
        $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', $key)->postJson($correctionUrl, $correction)->assertOk()->assertJsonPath('data.state', 'corrected');
        $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', $key)->postJson($correctionUrl, $correction)->assertOk();
        $this->assertSame(1, AttendanceEvent::query()->where('event_type', 'correction')->count());
        $range = '?start=2026-09-10&end=2026-09-10&store_id='.$store;
        $this->getJson('/api/v1/merchants/'.$merchant.'/reporting/reports/clock-in-out'.$range)->assertOk()->assertJsonPath('data.rows.0.worked_hours', fn ($hours) => (float) $hours === 7.5);
        $this->getJson('/api/v1/merchants/'.$merchant.'/reporting/reports/rota'.$range)->assertOk()->assertJsonPath('data.pagination.total', 1);

        $freeze = "/api/v1/merchants/{$merchant}/staff/{$staff->public_id}/payroll-input-snapshots";
        $snapshot = $this->actingAs($fixture['owner'])->postJson($freeze, ['period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertCreated()->json('data');
        $originalHash = $snapshot['content_hash'];
        $shift->forceFill(['break_seconds' => 3600])->save();
        $this->actingAs($fixture['owner'])->postJson($freeze, ['period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertCreated()->assertJsonPath('data.content_hash', $originalHash);
        $this->assertSame(1, PayrollInputSnapshot::query()->count());

        $csv = $this->actingAs($fixture['manager'])->get("/api/v1/merchants/{$merchant}/stores/{$store}/attendance/export.csv")->assertOk()->streamedContent();
        $this->assertStringContainsString('EMP-003', $csv);
        $otherCsv = $this->actingAs($fixture['manager'])->get("/api/v1/merchants/{$merchant}/stores/{$fixture['otherStore']->public_id}/attendance/export.csv")->assertOk()->streamedContent();
        $this->assertStringNotContainsString('EMP-003', $otherCsv);
    }

    /** @param array<string, mixed> $fixture */
    private function staff(array $fixture, string $number, int $userId): StaffProfile
    {
        return StaffProfile::query()->create(['merchant_id' => $fixture['merchant']->id, 'user_id' => $userId, 'default_store_id' => $fixture['store']->id, 'employee_number' => $number, 'display_name' => 'Ayesha Khan', 'employment_status' => 'active', 'hire_date' => '2025-01-01', 'pay_basis' => 'monthly', 'base_pay' => '75000.00', 'overtime_eligible' => true, 'version' => 1]);
    }
}
