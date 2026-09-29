<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Workforce\WorkforceService;
use App\Http\Controllers\Controller;
use App\Models\AttendanceShift;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\RotaShift;
use App\Models\StaffProfile;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkforcePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkforceController extends Controller
{
    public function staff(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $membership = $authorizer->authorize($this->user($request), $merchant, Permission::AttendanceOwn);
        $query = StaffProfile::query()->where('merchant_id', $merchant->id)->orderBy('display_name');
        if ($membership->role === MembershipRole::Cashier) {
            $query->where('user_id', $membership->user_id);
        }

        return response()->json(['data' => $query->get()->map(fn (StaffProfile $staff): array => $this->staffData($staff, $membership->role === MembershipRole::Owner))]);
    }

    public function createStaff(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::StaffManage);
        $data = $request->validate([
            'user_id' => ['required', 'string'], 'default_store_id' => ['nullable', 'string'],
            'employee_number' => ['required', 'string', 'max:40'], 'display_name' => ['required', 'string', 'max:160'],
            'hire_date' => ['required', 'date'], 'pay_basis' => ['required', 'in:monthly,hourly'],
            'base_pay' => ['required', 'decimal:0,2', 'min:0'], 'overtime_eligible' => ['required', 'boolean'],
        ]);
        $target = (new User)->resolveRouteBinding($data['user_id']);
        abort_unless($target instanceof User && MerchantMembership::query()->where('merchant_id', $merchant->id)->where('user_id', $target->id)->where('status', 'active')->exists(), 422, 'The selected user is not an active merchant member.');
        $storeId = null;
        if (isset($data['default_store_id'])) {
            $store = (new Store)->resolveRouteBinding($data['default_store_id']);
            abort_unless($store instanceof Store && $store->merchant_id === $merchant->id, 404);
            $storeId = $store->id;
        }
        $staff = StaffProfile::query()->create(['merchant_id' => $merchant->id, 'user_id' => $target->id, 'default_store_id' => $storeId, 'employee_number' => $data['employee_number'], 'display_name' => $data['display_name'], 'employment_status' => 'active', 'hire_date' => $data['hire_date'], 'pay_basis' => $data['pay_basis'], 'base_pay' => $data['base_pay'], 'overtime_eligible' => $data['overtime_eligible'], 'version' => 1]);

        return response()->json(['data' => $this->staffData($staff, true)], 201);
    }

    public function policy(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::AttendanceManage);
        $policy = WorkforcePolicy::query()->firstOrCreate(['merchant_id' => $merchant->id], ['grace_minutes' => 5, 'max_shift_minutes' => 960, 'break_required_after_minutes' => 300, 'overtime_after_minutes' => 480]);

        return response()->json(['data' => $policy]);
    }

    public function updatePolicy(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::StaffManage);
        $data = $request->validate(['grace_minutes' => ['required', 'integer', 'min:0', 'max:120'], 'max_shift_minutes' => ['required', 'integer', 'min:60', 'max:1440'], 'break_required_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'], 'overtime_after_minutes' => ['required', 'integer', 'min:1', 'max:1440']]);
        $policy = WorkforcePolicy::query()->updateOrCreate(['merchant_id' => $merchant->id], $data);

        return response()->json(['data' => $policy]);
    }

    public function rota(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::AttendanceManage, $store);
        $items = RotaShift::query()->with('staffProfile')->where('merchant_id', $merchant->id)->where('store_id', $store->id)->orderBy('starts_at')->limit(250)->get();

        return response()->json(['data' => $items]);
    }

    public function createRota(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, WorkforceService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::AttendanceManage, $store);
        $data = $request->validate(['staff_id' => ['required', 'string'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:500']]);
        $staff = $this->staffFor($merchant, $data['staff_id']);
        $shift = $service->createRota($merchant, $store, $staff, $user, CarbonImmutable::parse($data['starts_at']), CarbonImmutable::parse($data['ends_at']), $data['notes'] ?? null);

        return response()->json(['data' => $shift], 201);
    }

    public function clock(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, WorkforceService $service): JsonResponse
    {
        $user = $this->user($request);
        $membership = $authorizer->authorize($user, $merchant, Permission::AttendanceOwn, $store);
        $data = $request->validate(['staff_id' => ['nullable', 'string'], 'action' => ['required', 'in:clock_in,break_start,break_end,clock_out'], 'occurred_at' => ['required', 'date']]);
        $staff = isset($data['staff_id']) ? $this->staffFor($merchant, $data['staff_id']) : StaffProfile::query()->where('merchant_id', $merchant->id)->where('user_id', $user->id)->firstOrFail();
        if ($staff->user_id !== $user->id) {
            abort_unless($membership->role !== MembershipRole::Cashier, 403);
            $authorizer->authorize($user, $merchant, Permission::AttendanceManage, $store);
        }
        $result = $service->clock($merchant, $store, $staff, $user, $this->idempotencyKey($request), $data['action'], CarbonImmutable::parse($data['occurred_at']));

        return response()->json(['data' => $result['shift'], 'meta' => ['replayed' => $result['replayed']]], $result['replayed'] ? 200 : 201);
    }

    public function attendance(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $membership = $authorizer->authorize($this->user($request), $merchant, Permission::AttendanceOwn, $store);
        $query = AttendanceShift::query()->with('staffProfile')->where('merchant_id', $merchant->id)->where('store_id', $store->id)->latest('clocked_in_at')->limit(250);
        if ($membership->role === MembershipRole::Cashier) {
            $query->whereHas('staffProfile', fn ($q) => $q->where('user_id', $membership->user_id));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function correct(Request $request, Merchant $merchant, Store $store, AttendanceShift $attendanceShift, RoleAuthorizer $authorizer, WorkforceService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::AttendanceManage, $store);
        abort_unless($attendanceShift->merchant_id === $merchant->id && $attendanceShift->store_id === $store->id, 404);
        $data = $request->validate(['clocked_in_at' => ['required', 'date'], 'clocked_out_at' => ['required', 'date'], 'break_seconds' => ['required', 'integer', 'min:0', 'max:86400'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $shift = $service->correct($attendanceShift, $user, $this->idempotencyKey($request), CarbonImmutable::parse($data['clocked_in_at']), CarbonImmutable::parse($data['clocked_out_at']), $data['break_seconds'], $data['reason']);

        return response()->json(['data' => $shift]);
    }

    public function export(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): StreamedResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::AttendanceManage, $store);
        $rows = AttendanceShift::query()->with('staffProfile')->where('merchant_id', $merchant->id)->where('store_id', $store->id)->orderBy('clocked_in_at')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            abort_unless(is_resource($out), 500);
            fputcsv($out, ['employee_number', 'staff_name', 'clocked_in_at', 'clocked_out_at', 'break_seconds', 'state']);
            foreach ($rows as $row) {
                fputcsv($out, [$this->csv($row->staffProfile->employee_number), $this->csv($row->staffProfile->display_name), $row->clocked_in_at->toAtomString(), $row->clocked_out_at?->toAtomString(), $row->break_seconds, $row->state]);
            }
            fclose($out);
        }, 'attendance.csv', ['Cache-Control' => 'private, no-store', 'Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function leaveTypes(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::AttendanceOwn);

        return response()->json(['data' => LeaveType::query()->where('merchant_id', $merchant->id)->orderBy('name')->get()]);
    }

    public function createLeaveType(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::StaffManage);
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:40'], 'name' => ['required', 'string', 'max:120'], 'is_paid' => ['required', 'boolean'], 'is_active' => ['sometimes', 'boolean']]);
        $type = LeaveType::query()->create(['merchant_id' => $merchant->id] + $data);

        return response()->json(['data' => $type], 201);
    }

    public function leave(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $membership = $authorizer->authorize($this->user($request), $merchant, Permission::AttendanceOwn);
        $query = LeaveRequest::query()->with(['staffProfile', 'leaveType'])->where('merchant_id', $merchant->id)->latest();
        if ($membership->role === MembershipRole::Cashier) {
            $query->whereHas('staffProfile', fn ($q) => $q->where('user_id', $membership->user_id));
        }

        return response()->json(['data' => $query->limit(250)->get()]);
    }

    public function requestLeave(Request $request, Merchant $merchant, RoleAuthorizer $authorizer, WorkforceService $service): JsonResponse
    {
        $user = $this->user($request);
        $membership = $authorizer->authorize($user, $merchant, Permission::AttendanceOwn);
        $data = $request->validate(['staff_id' => ['nullable', 'string'], 'leave_type_id' => ['required', 'string'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:500']]);
        $staff = isset($data['staff_id']) ? $this->staffFor($merchant, $data['staff_id']) : StaffProfile::query()->where('merchant_id', $merchant->id)->where('user_id', $user->id)->firstOrFail();
        abort_unless($staff->user_id === $user->id || $membership->role !== MembershipRole::Cashier, 403);
        $type = (new LeaveType)->resolveRouteBinding($data['leave_type_id']);
        abort_unless($type instanceof LeaveType, 404);
        $leave = $service->requestLeave($merchant, $staff, $type, CarbonImmutable::parse($data['starts_on']), CarbonImmutable::parse($data['ends_on']), $data['note'] ?? null);

        return response()->json(['data' => $leave], 201);
    }

    public function decideLeave(Request $request, Merchant $merchant, LeaveRequest $leaveRequest, RoleAuthorizer $authorizer, WorkforceService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::AttendanceManage);
        abort_unless($leaveRequest->merchant_id === $merchant->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);

        return response()->json(['data' => $service->decideLeave($leaveRequest, $user, $data['decision'], $data['reason'])]);
    }

    public function cancelLeave(Request $request, Merchant $merchant, LeaveRequest $leaveRequest, RoleAuthorizer $authorizer, WorkforceService $service): JsonResponse
    {
        $user = $this->user($request);
        $membership = $authorizer->authorize($user, $merchant, Permission::AttendanceOwn);
        abort_unless($leaveRequest->merchant_id === $merchant->id, 404);
        $staff = StaffProfile::query()->findOrFail($leaveRequest->staff_profile_id);
        abort_unless($staff->user_id === $user->id || $membership->role !== MembershipRole::Cashier, 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        return response()->json(['data' => $service->cancelLeave($leaveRequest, $user, $data['reason'])]);
    }

    public function allocateLeave(Request $request, Merchant $merchant, StaffProfile $staffProfile, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::StaffManage);
        abort_unless($staffProfile->merchant_id === $merchant->id, 404);
        $data = $request->validate(['leave_type_id' => ['required', 'string'], 'year' => ['required', 'integer', 'min:2000', 'max:2200'], 'allocated_days' => ['required', 'decimal:0,2', 'min:0']]);
        $type = (new LeaveType)->resolveRouteBinding($data['leave_type_id']);
        abort_unless($type instanceof LeaveType && $type->merchant_id === $merchant->id, 404);
        $balance = LeaveBalance::query()->updateOrCreate(['merchant_id' => $merchant->id, 'staff_profile_id' => $staffProfile->id, 'leave_type_id' => $type->id, 'year' => $data['year']], ['allocated_days' => $data['allocated_days']]);

        return response()->json(['data' => $balance]);
    }

    public function freeze(Request $request, Merchant $merchant, StaffProfile $staffProfile, RoleAuthorizer $authorizer, WorkforceService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PayrollManage);
        abort_unless($staffProfile->merchant_id === $merchant->id, 404);
        $data = $request->validate(['period_start' => ['required', 'date'], 'period_end' => ['required', 'date']]);
        $snapshot = $service->freeze($staffProfile, $user, CarbonImmutable::parse($data['period_start']), CarbonImmutable::parse($data['period_end']));

        return response()->json(['data' => $snapshot], 201);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function staffFor(Merchant $merchant, string $id): StaffProfile
    {
        $staff = (new StaffProfile)->resolveRouteBinding($id);
        abort_unless($staff instanceof StaffProfile && $staff->merchant_id === $merchant->id, 404);

        return $staff;
    }

    private function idempotencyKey(Request $request): string
    {
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[0-9a-f-]{36}$/i', $key) === 1, 422, 'A UUID Idempotency-Key header is required.');

        return $key;
    }

    /** @return array<string, mixed> */
    private function staffData(StaffProfile $staff, bool $includePay): array
    {
        $data = ['id' => $staff->public_id, 'employee_number' => $staff->employee_number, 'display_name' => $staff->display_name, 'employment_status' => $staff->employment_status, 'hire_date' => $staff->hire_date->toDateString(), 'overtime_eligible' => $staff->overtime_eligible];
        if ($includePay) {
            $data += ['pay_basis' => $staff->pay_basis, 'base_pay' => $staff->base_pay];
        }

        return $data;
    }

    private function csv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
