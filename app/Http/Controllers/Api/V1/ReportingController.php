<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Reporting\StandardReportService;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\SavedReportFilter;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function show(Request $request, Merchant $merchant, string $report, RoleAuthorizer $authorizer, StandardReportService $service): JsonResponse
    {
        $user = $this->user($request);
        [$store,$permission] = $this->scope($request, $merchant);
        $authorizer->authorize($user, $merchant, $permission, $store);
        if ($report === 'workforce') {
            $authorizer->authorize($user, $merchant, Permission::PayrollManage);
        }$d = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start'], 'store_id' => ['nullable', 'string']]);

        return response()->json(['data' => $service->run($merchant, $store?->id, $report, CarbonImmutable::parse($d['start']), CarbonImmutable::parse($d['end']))]);
    }

    public function export(Request $request, Merchant $merchant, string $report, RoleAuthorizer $authorizer, StandardReportService $service): StreamedResponse
    {
        $user = $this->user($request);
        [$store,$permission] = $this->scope($request, $merchant);
        $authorizer->authorize($user, $merchant, $permission, $store);
        if ($report === 'workforce') {
            $authorizer->authorize($user, $merchant, Permission::PayrollManage);
        }$d = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start'], 'store_id' => ['nullable', 'string']]);
        $result = $service->run($merchant, $store?->id, $report, CarbonImmutable::parse($d['start']), CarbonImmutable::parse($d['end']));

        return response()->streamDownload(function () use ($result): void {
            $out = fopen('php://output', 'wb');
            abort_unless(is_resource($out), 500);
            fputcsv($out, ['metric', 'value']);
            foreach ($result['data'] as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    fputcsv($out, [$this->csv((string) $key), $this->csv((string) $value)]);
                }
            }fclose($out);
        }, $report.'-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function filters(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::ReportStore);

        return response()->json(['data' => SavedReportFilter::query()->where('merchant_id', $merchant->id)->where('user_id', $user->id)->orderBy('name')->get()]);
    }

    public function saveFilter(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::ReportStore);
        $d = $request->validate(['report_key' => ['required', 'in:'.implode(',', StandardReportService::KEYS)], 'name' => ['required', 'string', 'max:120'], 'filters' => ['required', 'array'], 'filters.start' => ['required', 'date'], 'filters.end' => ['required', 'date'], 'filters.store_id' => ['nullable', 'string']]);
        if (isset($d['filters']['store_id'])) {
            $store = (new Store)->resolveRouteBinding($d['filters']['store_id']);
            abort_unless($store instanceof Store && $store->merchant_id === $merchant->id, 404);
            $authorizer->authorize($user, $merchant, Permission::ReportStore, $store);
        }
        $filter = SavedReportFilter::query()->updateOrCreate(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'report_key' => $d['report_key'], 'name' => $d['name']], ['filters' => $d['filters']]);

        return response()->json(['data' => $filter], 201);
    }

    /** @return array{0:Store|null,1:Permission} */
    private function scope(Request $r, Merchant $m): array
    {
        $id = $r->query('store_id');
        if (! is_string($id) || $id === '') {
            return [null, Permission::ReportConsolidated];
        }$store = (new Store)->resolveRouteBinding($id);
        abort_unless($store instanceof Store && $store->merchant_id === $m->id, 404);

        return [$store, Permission::ReportStore];
    }

    private function user(Request $r): User
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }

    private function csv(string $v): string
    {
        return preg_match('/^[=+\-@]/', $v) === 1 ? "'".$v : $v;
    }
}
