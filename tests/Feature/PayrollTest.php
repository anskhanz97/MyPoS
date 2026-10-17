<?php

namespace Tests\Feature;

use App\Models\AdvanceAllocation;
use App\Models\AttendanceShift;
use App\Models\EmployeeAdvance;
use App\Models\PayrollItem;
use App\Models\PayrollPayout;
use App\Models\PayslipSnapshot;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_monthly_payroll_posts_immutable_inputs_allocates_advance_and_supports_partial_payout(): void
    {
        $f = $this->pricingFixture();
        $staff = $this->staff($f, 'monthly', '75000.00');
        $base = "/api/v1/merchants/{$f['merchant']->public_id}";
        $this->actingAs($f['owner'])->postJson($base.'/staff/'.$staff->public_id.'/advances', ['amount' => '5000.00', 'reason' => 'Emergency salary advance', 'issued_on' => '2026-09-05'])->assertCreated();
        $adjustments = [$staff->public_id => [
            ['kind' => 'allowance', 'amount' => '5000.00', 'label' => 'Performance allowance', 'provenance' => 'entered'],
            ['kind' => 'deduction', 'amount' => '1000.00', 'label' => 'Unpaid equipment', 'provenance' => 'entered', 'reason' => 'Employee-approved recovery'],
            ['kind' => 'statutory', 'amount' => '2000.00', 'label' => 'Entered statutory amount', 'provenance' => 'imported'],
            ['kind' => 'advance', 'amount' => '3000.00', 'label' => 'Advance recovery', 'provenance' => 'entered'],
        ]];
        $run = $this->actingAs($f['owner'])->postJson($base.'/payroll-runs', ['period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'adjustments' => $adjustments])->assertCreated()->assertJsonPath('data.items.0.net_amount', '74000.00')->json('data');
        $this->actingAs($f['manager'])->postJson($base.'/payroll-runs')->assertForbidden();
        $posted = $this->actingAs($f['owner'])->postJson($base.'/payroll-runs/'.$run['public_id'].'/post', ['expected_version' => 1])->assertOk()->assertJsonPath('data.state', 'posted')->json('data');
        $this->actingAs($f['owner'])->postJson($base.'/payroll-runs/'.$run['public_id'].'/post', ['expected_version' => 1])->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertSame(1, AdvanceAllocation::query()->count());
        $this->assertSame('2000.00', EmployeeAdvance::query()->firstOrFail()->outstanding_amount);
        $this->assertSame(1, PayslipSnapshot::query()->count());

        $item = $posted['items'][0];
        $key = (string) Str::uuid();
        $payout = ['amount' => '40000.00', 'payment_method' => 'external_recorded', 'reference' => 'BANK-REC-1', 'paid_at' => now()->toAtomString()];
        $this->actingAs($f['owner'])->withHeader('Idempotency-Key', $key)->postJson($base.'/payroll-items/'.$item['public_id'].'/payouts', $payout)->assertCreated();
        $this->actingAs($f['owner'])->withHeader('Idempotency-Key', $key)->postJson($base.'/payroll-items/'.$item['public_id'].'/payouts', $payout)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->assertSame('40000.00', PayrollItem::query()->firstOrFail()->paid_amount);
        $this->assertSame(1, PayrollPayout::query()->count());
        $range = '?start=2026-09-01&end=2026-09-30&store_id='.$f['store']->public_id;
        $this->getJson($base.'/reporting/reports/payroll-detail'.$range)->assertOk()->assertJsonPath('data.summary.gross_amount', '80000.00')->assertJsonPath('data.summary.statutory_amount', '2000.00')->assertJsonPath('data.summary.paid_amount', '40000.00')->assertJsonPath('data.summary.outstanding_amount', '34000.00');
        $this->getJson($base.'/reporting/reports/profit-loss'.$range)->assertOk()->assertJsonPath('data.summary.posted_payroll', '80000.00')->assertJsonPath('data.summary.operating_profit', '-80000.00');
        $staff->forceFill(['default_store_id' => $f['otherStore']->id, 'display_name' => 'Renamed employee'])->save();
        $this->getJson($base.'/reporting/reports/payroll-detail'.$range)->assertOk()->assertJsonPath('data.rows.0.label', 'Ayesha Khan')->assertJsonPath('data.summary.gross_amount', '80000.00');
        $this->getJson($base.'/reporting/reports/profit-loss'.$range)->assertOk()->assertJsonPath('data.summary.posted_payroll', '80000.00');
        $snapshot = DB::table('payroll_input_snapshots')->where('staff_profile_id', $staff->id)->first();
        $legacyPayload = json_decode($snapshot->payload, true, flags: JSON_THROW_ON_ERROR);
        unset($legacyPayload['default_store_id'], $legacyPayload['staff_name']);
        $legacyJson = json_encode($legacyPayload, JSON_THROW_ON_ERROR);
        DB::table('payroll_input_snapshots')->where('id', $snapshot->id)->update(['payload' => $legacyJson, 'content_hash' => hash('sha256', $legacyJson)]);
        $this->getJson($base.'/reporting/reports/profit-loss'.$range)->assertOk()->assertJsonPath('data.summary.unallocated_payroll', '80000.00')->assertJsonPath('data.summary.operating_profit', null);
        $this->actingAs($f['owner'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/payroll-items/'.$item['public_id'].'/payouts', $payout)->assertConflict();
        $this->actingAs($f['cashier'])->get($base.'/payroll-items/'.$item['public_id'].'/payslip.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($f['manager'])->get($base.'/payroll-items/'.$item['public_id'].'/payslip.pdf')->assertForbidden();
    }

    public function test_hourly_pay_and_overtime_use_decimal_golden_formula(): void
    {
        $f = $this->pricingFixture();
        $staff = $this->staff($f, 'hourly', '500.00');
        AttendanceShift::query()->create(['merchant_id' => $f['merchant']->id, 'store_id' => $f['store']->id, 'staff_profile_id' => $staff->id, 'state' => 'complete', 'clocked_in_at' => '2026-09-10 08:00:00', 'clocked_out_at' => '2026-09-10 18:00:00', 'break_seconds' => 0]);
        $base = "/api/v1/merchants/{$f['merchant']->public_id}";
        $this->actingAs($f['owner'])->postJson($base.'/payroll-runs', ['period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertCreated()->assertJsonPath('data.items.0.base_amount', '5000.00')->assertJsonPath('data.items.0.overtime_amount', '1500.00')->assertJsonPath('data.items.0.net_amount', '6500.00');
    }

    /** @param array<string,mixed> $f */
    private function staff(array $f, string $basis, string $pay): StaffProfile
    {
        return StaffProfile::query()->create(['merchant_id' => $f['merchant']->id, 'user_id' => $f['cashier']->id, 'default_store_id' => $f['store']->id, 'employee_number' => 'PAY-001', 'display_name' => 'Ayesha Khan', 'employment_status' => 'active', 'hire_date' => '2025-01-01', 'pay_basis' => $basis, 'base_pay' => $pay, 'overtime_eligible' => true, 'version' => 1]);
    }
}
