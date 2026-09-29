<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Payroll\PayrollService;
use App\Domain\Payroll\PayslipPdf;
use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\EmployeeAdvance;
use App\Models\Merchant;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayslipSnapshot;
use App\Models\StaffPayTerm;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PayrollController extends Controller
{
    public function terms(Request $request, Merchant $merchant, StaffProfile $staffProfile, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PayrollManage);
        $this->staffScope($merchant, $staffProfile);
        $data = $request->validate(['effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'], 'pay_basis' => ['required', 'in:monthly,hourly'], 'base_rate' => ['required', 'decimal:0,2', 'gt:0'], 'standard_monthly_hours' => ['sometimes', 'decimal:0,2', 'gt:0'], 'overtime_multiplier' => ['sometimes', 'decimal:0,4', 'gte:1']]);
        abort_if(StaffPayTerm::query()->where('staff_profile_id', $staffProfile->id)->where('effective_from', '<=', $data['effective_until'] ?? '9999-12-31')->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $data['effective_from']))->exists(), 409, 'Pay terms may not overlap.');
        $term = StaffPayTerm::query()->create(['merchant_id' => $merchant->id, 'staff_profile_id' => $staffProfile->id, 'created_by_user_id' => $user->id] + $data);

        return response()->json(['data' => $term], 201);
    }

    public function advance(Request $request, Merchant $merchant, StaffProfile $staffProfile, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PayrollManage);
        $this->staffScope($merchant, $staffProfile);
        $data = $request->validate(['amount' => ['required', 'decimal:0,2', 'gt:0'], 'reason' => ['required', 'string', 'min:5', 'max:500'], 'issued_on' => ['required', 'date']]);
        $advance = EmployeeAdvance::query()->create(['merchant_id' => $merchant->id, 'staff_profile_id' => $staffProfile->id, 'original_amount' => $data['amount'], 'outstanding_amount' => $data['amount'], 'reason' => $data['reason'], 'issued_on' => $data['issued_on'], 'state' => 'open', 'created_by_user_id' => $user->id]);

        return response()->json(['data' => $advance], 201);
    }

    public function index(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::PayrollManage);

        return response()->json(['data' => PayrollRun::query()->with('items')->where('merchant_id', $merchant->id)->latest('period_end')->limit(100)->get()]);
    }

    public function create(Request $request, Merchant $merchant, RoleAuthorizer $authorizer, PayrollService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PayrollManage);
        $data = $request->validate(['period_start' => ['required', 'date'], 'period_end' => ['required', 'date'], 'adjustments' => ['sometimes', 'array'], 'adjustments.*' => ['array'], 'adjustments.*.*.kind' => ['required', 'in:allowance,deduction,statutory,advance'], 'adjustments.*.*.amount' => ['required', 'decimal:0,2', 'gt:0'], 'adjustments.*.*.label' => ['required', 'string', 'max:160'], 'adjustments.*.*.provenance' => ['required', 'in:entered,imported'], 'adjustments.*.*.reason' => ['nullable', 'string', 'max:500']]);
        $run = $service->draft($merchant, $user, CarbonImmutable::parse($data['period_start']), CarbonImmutable::parse($data['period_end']), $data['adjustments'] ?? []);

        return response()->json(['data' => $run], 201);
    }

    public function show(Request $request, Merchant $merchant, PayrollRun $payrollRun, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::PayrollManage);
        abort_unless($payrollRun->merchant_id === $merchant->id, 404);

        return response()->json(['data' => $payrollRun->load(['items.staffProfile', 'items.adjustments', 'items.payouts'])]);
    }

    public function post(Request $request, Merchant $merchant, PayrollRun $payrollRun, RoleAuthorizer $authorizer, PayrollService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PayrollManage);
        abort_unless($payrollRun->merchant_id === $merchant->id, 404);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);

        return response()->json(['data' => $service->post($payrollRun, $user, $data['expected_version'])]);
    }

    public function payout(Request $request, Merchant $merchant, PayrollItem $payrollItem, RoleAuthorizer $authorizer, PayrollService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PayrollManage);
        abort_unless($payrollItem->merchant_id === $merchant->id, 404);
        $data = $request->validate(['amount' => ['required', 'decimal:0,2', 'gt:0'], 'payment_method' => ['required', 'in:cash,external_recorded'], 'reference' => ['nullable', 'string', 'max:160'], 'paid_at' => ['required', 'date'], 'cash_session_id' => ['nullable', 'string']]);
        $session = null;
        if (isset($data['cash_session_id'])) {
            $session = (new CashSession)->resolveRouteBinding($data['cash_session_id']);
            abort_unless($session instanceof CashSession, 404);
        }
        $result = $service->payout($payrollItem, $user, $this->key($request), $data['amount'], $data['payment_method'], $data['reference'] ?? null, CarbonImmutable::parse($data['paid_at']), $session);

        return response()->json(['data' => $result['payout'], 'meta' => ['replayed' => $result['replayed']]], $result['replayed'] ? 200 : 201);
    }

    public function payslip(Request $request, Merchant $merchant, PayrollItem $payrollItem, RoleAuthorizer $authorizer, PayslipPdf $pdf): Response
    {
        $user = $this->user($request);
        $membership = $authorizer->authorize($user, $merchant, Permission::PayrollOwn);
        abort_unless($payrollItem->merchant_id === $merchant->id, 404);
        if ($membership->role->value !== 'owner') {
            abort_unless(StaffProfile::query()->whereKey($payrollItem->staff_profile_id)->where('user_id', $user->id)->exists(), 403);
        }
        $snapshot = PayslipSnapshot::query()->where('payroll_item_id', $payrollItem->id)->firstOrFail();

        return response($pdf->render($snapshot), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="payslip-'.$payrollItem->public_id.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function staffScope(Merchant $merchant, StaffProfile $staff): void
    {
        abort_unless($staff->merchant_id === $merchant->id, 404);
    }

    private function key(Request $request): string
    {
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[0-9a-f-]{36}$/i', $key) === 1, 422, 'A UUID Idempotency-Key header is required.');

        return $key;
    }
}
