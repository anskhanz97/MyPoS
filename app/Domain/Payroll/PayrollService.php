<?php

namespace App\Domain\Payroll;

use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\PostCashMovement;
use App\Domain\Workforce\WorkforceService;
use App\Models\AdvanceAllocation;
use App\Models\CashSession;
use App\Models\EmployeeAdvance;
use App\Models\Merchant;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollPayout;
use App\Models\PayrollRun;
use App\Models\PayslipSnapshot;
use App\Models\StaffPayTerm;
use App\Models\StaffProfile;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PayrollService
{
    public function __construct(private readonly WorkforceService $workforce, private readonly PostCashMovement $cash) {}

    /** @param array<string, list<array{kind:string,amount:string,label:string,provenance:string,reason?:string}>> $adjustments */
    public function draft(Merchant $merchant, User $actor, CarbonImmutable $start, CarbonImmutable $end, array $adjustments = []): PayrollRun
    {
        abort_unless($end->greaterThanOrEqualTo($start), 422, 'Payroll period end must not precede start.');

        return DB::transaction(function () use ($merchant, $actor, $start, $end, $adjustments): PayrollRun {
            $run = PayrollRun::query()->where('merchant_id', $merchant->id)->where('period_start', $start->toDateString())->where('period_end', $end->toDateString())->lockForUpdate()->first();
            if ($run instanceof PayrollRun) {
                return $run->load(['items.staffProfile', 'items.adjustments']);
            }
            $run = PayrollRun::query()->create(['merchant_id' => $merchant->id, 'period_start' => $start, 'period_end' => $end, 'state' => 'draft', 'version' => 1, 'created_by_user_id' => $actor->id]);
            $staffMembers = StaffProfile::query()->where('merchant_id', $merchant->id)->where('employment_status', 'active')->orderBy('id')->get();
            abort_if($staffMembers->isEmpty(), 422, 'No active staff records exist for this payroll period.');
            foreach ($staffMembers as $staff) {
                $snapshot = $this->workforce->freeze($staff, $actor, $start, $end);
                $lines = $adjustments[$staff->public_id] ?? [];
                $amounts = $this->calculate($staff, $snapshot->payload, $start, $end, $lines);
                $item = PayrollItem::query()->create(['merchant_id' => $merchant->id, 'payroll_run_id' => $run->id, 'staff_profile_id' => $staff->id, 'payroll_input_snapshot_id' => $snapshot->id, 'state' => 'draft', 'paid_amount' => '0.00', 'version' => 1] + $amounts);
                foreach ($lines as $line) {
                    PayrollAdjustment::query()->create(['merchant_id' => $merchant->id, 'payroll_item_id' => $item->id, 'kind' => $line['kind'], 'amount' => BigDecimal::of($line['amount'])->abs()->toScale(2, RoundingMode::HalfUp), 'provenance' => $line['provenance'], 'label' => $line['label'], 'reason' => $line['reason'] ?? null, 'created_by_user_id' => $actor->id]);
                }
            }

            return $run->load(['items.staffProfile', 'items.adjustments']);
        }, attempts: 3);
    }

    public function post(PayrollRun $run, User $actor, int $expectedVersion): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor, $expectedVersion): PayrollRun {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->state === 'posted') {
                return $locked->load(['items.staffProfile', 'items.adjustments']);
            }
            abort_unless($locked->state === 'draft' && $locked->version === $expectedVersion, 409, 'The payroll run changed or is no longer draft.');
            $items = PayrollItem::query()->where('payroll_run_id', $locked->id)->lockForUpdate()->get();
            foreach ($items as $item) {
                $remaining = BigDecimal::of($item->advance_amount);
                $advances = EmployeeAdvance::query()->where('staff_profile_id', $item->staff_profile_id)->where('state', 'open')->where('outstanding_amount', '>', 0)->orderBy('issued_on')->lockForUpdate()->get();
                foreach ($advances as $advance) {
                    if ($remaining->isZero()) {
                        break;
                    }
                    $advanceOutstanding = BigDecimal::of((string) $advance->outstanding_amount);
                    $amount = ($advanceOutstanding->isLessThan($remaining) ? $advanceOutstanding : $remaining)->toScale(2);
                    AdvanceAllocation::query()->create(['merchant_id' => $locked->merchant_id, 'employee_advance_id' => $advance->id, 'payroll_item_id' => $item->id, 'amount' => $amount, 'allocated_at' => now()]);
                    $outstanding = $advanceOutstanding->minus($amount)->toScale(2);
                    $advance->forceFill(['outstanding_amount' => $outstanding, 'state' => $outstanding->isZero() ? 'settled' : 'open'])->save();
                    $remaining = $remaining->minus($amount);
                }
                abort_unless($remaining->isZero(), 409, 'An advance balance changed before payroll posting. Recalculate the run.');
                $item->forceFill(['state' => 'posted', 'version' => $item->version + 1])->save();
                $staff = $item->staffProfile()->firstOrFail();
                $payload = ['run_id' => $locked->public_id, 'period_start' => $locked->period_start->toDateString(), 'period_end' => $locked->period_end->toDateString(), 'staff' => ['id' => $staff->public_id, 'name' => $staff->display_name, 'employee_number' => $staff->employee_number], 'calculation' => $item->calculation, 'totals' => ['gross' => $item->gross_amount, 'deductions' => BigDecimal::of((string) $item->deduction_amount)->plus((string) $item->statutory_amount)->plus((string) $item->advance_amount)->toScale(2), 'net' => $item->net_amount, 'paid' => '0.00', 'outstanding' => $item->net_amount]];
                $json = json_encode($payload, JSON_THROW_ON_ERROR);
                PayslipSnapshot::query()->create(['merchant_id' => $locked->merchant_id, 'payroll_item_id' => $item->id, 'payload' => $payload, 'content_hash' => hash('sha256', $json), 'created_at' => now()]);
            }
            $locked->forceFill(['state' => 'posted', 'posted_by_user_id' => $actor->id, 'posted_at' => now(), 'version' => $locked->version + 1])->save();

            return $locked->refresh()->load(['items.staffProfile', 'items.adjustments']);
        }, attempts: 3);
    }

    /** @return array{payout:PayrollPayout,replayed:bool} */
    public function payout(PayrollItem $item, User $actor, string $key, string $amount, string $method, ?string $reference, CarbonImmutable $paidAt, ?CashSession $cashSession): array
    {
        $money = BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);
        abort_unless($money->isPositive(), 422, 'Payout amount must be positive.');
        $hash = hash('sha256', json_encode([$item->public_id, (string) $money, $method, $reference, $paidAt->toAtomString(), $cashSession?->public_id], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($item, $actor, $key, $money, $method, $reference, $paidAt, $cashSession, $hash): array {
            $existing = PayrollPayout::query()->where('merchant_id', $item->merchant_id)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing instanceof PayrollPayout) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'The payout identity was reused with different data.');

                return ['payout' => $existing, 'replayed' => true];
            }
            $locked = PayrollItem::query()->lockForUpdate()->findOrFail($item->id);
            abort_unless($locked->state === 'posted', 409, 'Only posted payroll can be paid.');
            $outstanding = BigDecimal::of((string) $locked->net_amount)->minus((string) $locked->paid_amount);
            abort_if($money->isGreaterThan($outstanding), 409, 'Payout exceeds the payroll liability.');
            abort_unless(in_array($method, ['cash', 'external_recorded'], true), 422);
            abort_unless(($method === 'cash') === ($cashSession instanceof CashSession), 422, 'Cash payouts require a cash session; external records must not include one.');
            $payout = PayrollPayout::query()->create(['merchant_id' => $locked->merchant_id, 'payroll_item_id' => $locked->id, 'cash_session_id' => $cashSession?->id, 'recorded_by_user_id' => $actor->id, 'idempotency_key' => $key, 'payload_hash' => $hash, 'amount' => $money, 'payment_method' => $method, 'reference' => $reference, 'paid_at' => $paidAt]);
            if ($cashSession instanceof CashSession) {
                abort_unless($cashSession->merchant_id === $locked->merchant_id, 404);
                $this->cash->handle($cashSession, $actor, $key, CashMovementKind::PayrollPayout, (string) $money, 'Payroll payout', $paidAt->toAtomString(), 'payroll_payout', $payout->public_id, true);
            }
            $newPaid = BigDecimal::of((string) $locked->paid_amount)->plus($money)->toScale(2);
            $locked->forceFill(['paid_amount' => $newPaid, 'version' => $locked->version + 1])->save();

            return ['payout' => $payout, 'replayed' => false];
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array{kind:string, amount:string, label:string, provenance:string, reason?:string}>  $lines
     * @return array<string, mixed>
     */
    private function calculate(StaffProfile $staff, array $snapshot, CarbonImmutable $start, CarbonImmutable $end, array $lines): array
    {
        $term = StaffPayTerm::query()->where('staff_profile_id', $staff->id)->where('effective_from', '<=', $end->toDateString())->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $start->toDateString()))->latest('effective_from')->first();
        $basis = $term instanceof StaffPayTerm ? $term->pay_basis : (string) $snapshot['pay_basis'];
        $rate = BigDecimal::of($term instanceof StaffPayTerm ? (string) $term->base_rate : (string) $snapshot['base_pay']);
        $monthlyHours = BigDecimal::of($term instanceof StaffPayTerm ? (string) $term->standard_monthly_hours : '173.33');
        $multiplier = BigDecimal::of($term instanceof StaffPayTerm ? (string) $term->overtime_multiplier : '1.5000');
        $workedHours = BigDecimal::of((string) $snapshot['worked_minutes'])->dividedBy(60, 6, RoundingMode::HalfUp);
        $hourlyRate = $basis === 'hourly' ? $rate : $rate->dividedBy($monthlyHours, 6, RoundingMode::HalfUp);
        $base = $basis === 'hourly' ? $workedHours->multipliedBy($rate) : $rate;
        $overtime = BigDecimal::of((string) $snapshot['overtime_minutes'])->dividedBy(60, 6, RoundingMode::HalfUp)->multipliedBy($hourlyRate)->multipliedBy($multiplier);
        $periodDays = BigDecimal::of((string) ($start->diffInDays($end) + 1));
        $unpaid = $basis === 'monthly' ? $rate->dividedBy($periodDays, 6, RoundingMode::HalfUp)->multipliedBy((string) ($snapshot['unpaid_leave_days'] ?? '0')) : BigDecimal::zero();
        $allowance = BigDecimal::zero();
        $deduction = $unpaid;
        $statutory = BigDecimal::zero();
        $advance = BigDecimal::zero();
        foreach ($lines as $line) {
            $value = BigDecimal::of($line['amount'])->abs();
            match ($line['kind']) {
                'allowance' => $allowance = $allowance->plus($value), 'deduction' => $deduction = $deduction->plus($value), 'statutory' => $statutory = $statutory->plus($value), 'advance' => $advance = $advance->plus($value), default => abort(422, 'Unknown payroll adjustment kind.')
            };
            abort_unless(in_array($line['provenance'], ['entered', 'imported'], true), 422, 'Payroll adjustment provenance must be entered or imported.');
        }
        $availableAdvance = EmployeeAdvance::query()->where('staff_profile_id', $staff->id)->where('state', 'open')->sum('outstanding_amount');
        abort_if($advance->isGreaterThan((string) $availableAdvance), 422, 'Advance deduction exceeds the outstanding advance balance.');
        $gross = $base->plus($overtime)->plus($allowance);
        $net = $gross->minus($deduction)->minus($statutory)->minus($advance);
        abort_if($net->isNegative(), 422, 'Payroll deductions cannot create a negative net salary.');
        $fixed = fn (BigDecimal $value): string => (string) $value->toScale(2, RoundingMode::HalfUp);

        return ['base_amount' => $fixed($base), 'overtime_amount' => $fixed($overtime), 'allowance_amount' => $fixed($allowance), 'deduction_amount' => $fixed($deduction), 'statutory_amount' => $fixed($statutory), 'advance_amount' => $fixed($advance), 'gross_amount' => $fixed($gross), 'net_amount' => $fixed($net), 'calculation' => ['pay_basis' => $basis, 'rate' => $fixed($rate), 'worked_minutes' => $snapshot['worked_minutes'], 'overtime_minutes' => $snapshot['overtime_minutes'], 'overtime_multiplier' => (string) $multiplier, 'unpaid_leave_days' => $snapshot['unpaid_leave_days'] ?? '0.00', 'rounding' => 'PKR half-up to 2 decimals', 'adjustments' => $lines]];
    }
}
