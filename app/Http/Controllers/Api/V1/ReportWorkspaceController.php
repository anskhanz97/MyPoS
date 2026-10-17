<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Reporting\ReportAccess;
use App\Domain\Reporting\ReportCatalog;
use App\Domain\Reporting\ReportQuery;
use App\Domain\Reporting\ReportSnapshots;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessReportExport;
use App\Models\DataTransferJob;
use App\Models\Merchant;
use App\Models\SavedReportFilter;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReportWorkspaceController extends Controller
{
    public function catalog(Request $request, Merchant $merchant, RoleAuthorizer $auth, ReportAccess $access): JsonResponse
    {
        $user = $this->user($request);
        $membership = $auth->authorize($user, $merchant, Permission::ReportStore);
        $stores = Store::query()->where('merchant_id', $merchant->id);
        if ($membership->role->value !== 'owner') {
            $stores->whereIn('id', DB::table('store_assignments')->where('merchant_membership_id', $membership->id)->select('store_id'));
        }
        $assigned = $stores->orderBy('name')->get();
        $reports = [];
        foreach (ReportCatalog::all() as $report) {
            try {
                $access->authorize($user, $merchant, $assigned->first(), $report['key']);
                $reports[] = $report;
            } catch (HttpException $e) {
                if (! in_array($e->getStatusCode(), [403, 404], true)) {
                    throw $e;
                }
            }
        }
        $lookups = [];
        foreach (['product_categories', 'reporting_categories', 'product_brands', 'product_reporting_types', 'register_groups'] as $table) {
            $lookups[$table] = DB::table($table)->where('merchant_id', $merchant->id)->where(fn ($q) => $q->whereNull('store_id')->orWhereIn('store_id', $assigned->pluck('id')))->orderBy('name')->get()->map(fn ($r) => ['id' => Uuid::fromBytes($r->public_id)->toString(), 'name' => $r->name, 'code' => $r->code, 'classification' => $r->classification, 'archived' => $r->archived_at !== null])->all();
        }
        $lookups['registers'] = DB::table('registers')->where('merchant_id', $merchant->id)->whereIn('store_id', $assigned->pluck('id'))->orderBy('name')->get()->map(fn ($r) => ['id' => Uuid::fromBytes($r->public_id)->toString(), 'name' => $r->name, 'store_id' => $r->store_id])->all();
        $lookups['employees'] = DB::table('merchant_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.merchant_id', $merchant->id)->where('m.status', 'active')->select('u.id', 'm.public_id', 'u.name')->get()->map(fn ($r) => ['id' => Uuid::fromBytes($r->public_id)->toString(), 'name' => $r->name])->all();

        return response()->json(['data' => ['reports' => $reports, 'stores' => $assigned->map(fn (Store $s) => ['id' => $s->public_id, 'name' => $s->name, 'timezone' => $s->timezone]),
            'role' => $membership->role->value, 'can_consolidate' => $membership->role->value === 'owner', 'lookups' => $lookups, 'definitions' => ReportCatalog::definitions()]]);
    }

    public function show(Request $request, Merchant $merchant, string $report, ReportAccess $access, ReportQuery $query): JsonResponse
    {
        [$store, $filters] = $this->filters($request, $merchant);
        $scope = $access->authorize($this->user($request), $merchant, $store, $report);
        if (str_ends_with($request->path(), '/rows')) {
            $filters['detail'] = true;
        }

        return response()->json(['data' => $query->run($merchant, $store, $report, $filters, $scope)], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function export(Request $request, Merchant $merchant, string $report, ReportAccess $access): JsonResponse
    {
        [$store, $filters] = $this->filters($request, $merchant);
        $access->authorize($this->user($request), $merchant, $store, $report, true);
        $data = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])], 'summary_only' => ['sometimes', 'boolean']]);
        $job = DataTransferJob::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store?->id, 'actor_user_id' => $this->user($request)->id, 'direction' => 'export', 'resource' => 'report_workspace', 'state' => 'queued', 'schema_version' => 2, 'disk' => 'local', 'options' => ['report_key' => $report, 'filters' => $filters, 'format' => $data['format'], 'summary_only' => $data['summary_only'] ?? false]]);
        ProcessReportExport::dispatch($job->id);

        return response()->json(['data' => $job], 202);
    }

    public function exports(Request $request, Merchant $merchant, ReportAccess $access): JsonResponse
    {
        $jobs = DataTransferJob::query()->where('merchant_id', $merchant->id)->where('actor_user_id', $this->user($request)->id)->where('resource', 'report_workspace')->latest('id')->limit(20)->get();
        $visible = $jobs->filter(function (DataTransferJob $job) use ($request, $merchant, $access): bool {
            try {
                $this->authorizeJob($request, $merchant, $job, $access);

                return true;
            } catch (HttpException) {
                return false;
            }
        });

        return response()->json(['data' => $visible->values()->map(function (DataTransferJob $job): array {
            $data = $job->toArray();
            $data['download_available'] = $job->state === 'ready' && $job->artifactIsCurrent() && $job->artifact_path !== null && Storage::disk($job->disk)->exists($job->artifact_path);
            if ($job->state === 'ready' && ! $data['download_available']) {
                $data['state'] = $job->artifactIsCurrent() ? 'unavailable' : 'expired';
                $data['failure_message'] = 'This file is no longer available. Request a new export.';
            }

            return $data;
        })]);
    }

    public function download(Request $request, Merchant $merchant, DataTransferJob $dataTransferJob, ReportAccess $access): StreamedResponse
    {
        $this->authorizeJob($request, $merchant, $dataTransferJob, $access);
        abort_unless($dataTransferJob->state === 'ready' && $dataTransferJob->artifact_path !== null, 404);
        abort_unless($dataTransferJob->artifactIsCurrent(), 410, 'This export has expired. Request a new export.');
        abort_unless(Storage::disk($dataTransferJob->disk)->exists($dataTransferJob->artifact_path), 410, 'This export file is no longer available. Request a new export.');
        $options = $dataTransferJob->optionsData();
        $filename = $options['report_key'].'-'.$options['filters']['start'].'-to-'.$options['filters']['end'].'.'.$options['format'];

        return Storage::disk($dataTransferJob->disk)->download($dataTransferJob->artifact_path, $filename, ['Cache-Control' => 'private, no-store']);
    }

    private function authorizeJob(Request $request, Merchant $merchant, DataTransferJob $job, ReportAccess $access): void
    {
        abort_unless($job->merchant_id === $merchant->id && $job->actor_user_id === $this->user($request)->id && $job->resource === 'report_workspace', 404);
        $store = $job->store_id === null ? null : Store::query()->where('merchant_id', $merchant->id)->findOrFail($job->store_id);
        $access->authorize($this->user($request), $merchant, $store, $job->optionsData()['report_key'], true);
    }

    public function saved(Request $request, Merchant $merchant, RoleAuthorizer $auth): JsonResponse
    {
        $auth->authorize($this->user($request), $merchant, Permission::ReportStore);

        return response()->json(['data' => SavedReportFilter::query()->where('merchant_id', $merchant->id)->where('user_id', $this->user($request)->id)->orderBy('name')->get()]);
    }

    public function save(Request $request, Merchant $merchant, ReportAccess $access, ?SavedReportFilter $savedReportFilter = null): JsonResponse
    {
        $data = $request->validate(['report_key' => ['required', Rule::in(array_keys(ReportCatalog::all()))], 'name' => ['required', 'string', 'max:120'], 'filters' => ['required', 'array']]);
        $filterRequest = Request::create('/', 'GET', $data['filters']);
        [$store] = $this->filters($filterRequest, $merchant);
        $access->authorize($this->user($request), $merchant, $store, $data['report_key']);
        if ($savedReportFilter !== null) {
            abort_unless($savedReportFilter->merchant_id === $merchant->id && $savedReportFilter->user_id === $this->user($request)->id, 404);
            $savedReportFilter->update($data);
        } else {
            $savedReportFilter = SavedReportFilter::query()->updateOrCreate(['merchant_id' => $merchant->id, 'user_id' => $this->user($request)->id, 'report_key' => $data['report_key'], 'name' => $data['name']], ['filters' => $data['filters']]);
        }

        return response()->json(['data' => $savedReportFilter], 201);
    }

    public function deleteSaved(Request $request, Merchant $merchant, SavedReportFilter $savedReportFilter): JsonResponse
    {
        abort_unless($savedReportFilter->merchant_id === $merchant->id && $savedReportFilter->user_id === $this->user($request)->id, 404);
        $savedReportFilter->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array{0: Store|null, 1: array<string, mixed>}
     */
    public function filters(Request $request, Merchant $merchant): array
    {
        $request->mergeIfMissing(['start' => now($merchant->timezone)->subDays(29)->toDateString(), 'end' => now($merchant->timezone)->toDateString()]);
        $rules = ['start' => ['required', 'date_format:Y-m-d'], 'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'], 'store_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:120'], 'sort' => ['nullable', 'string', 'max:60'], 'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:200'], 'group_key' => ['nullable', 'string', 'max:120'],
            'group_by' => ['nullable', Rule::in(['day', 'week', 'month', 'year'])], 'comparison' => ['nullable', Rule::in(['previous_period', 'previous_year'])]];
        $references = ['register_id' => 'registers', 'employee_id' => 'users', 'session_id' => 'cash_sessions', 'variant_id' => 'product_variants', 'inventory_location_id' => 'inventory_locations'] + ReportSnapshots::DIMENSIONS;
        foreach ($references as $name => $table) {
            $rules[$name] = ['nullable', 'uuid'];
        }
        $f = array_filter($request->validate($rules), fn ($v) => $v !== null && $v !== '');
        abort_if(CarbonImmutable::parse($f['start'])->diffInDays(CarbonImmutable::parse($f['end'])) > 365, 422, 'Report range cannot exceed 366 inclusive days.');
        $store = isset($f['store_id']) ? Store::query()->where('merchant_id', $merchant->id)->where('public_id', Uuid::fromString($f['store_id'])->getBytes())->firstOrFail() : null;
        foreach ($references as $name => $table) {
            if (! isset($f[$name])) {
                continue;
            }
            $q = $table === 'users' ? DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('public_id', Uuid::fromString($f[$name])->getBytes()) : DB::table($table)->where('public_id', Uuid::fromString($f[$name])->getBytes());
            if ($table === 'users') {
                $q->where('status', 'active');
            } else {
                $q->where('merchant_id', $merchant->id);
            }
            if ($store !== null && in_array($table, ['registers', 'cash_sessions', 'inventory_locations'], true)) {
                $q->where('store_id', $store->id);
            }
            $row = $q->first();
            abort_unless($row !== null, 404);
            $f[$name] = $table === 'users' ? $row->user_id : $row->id;
        }
        if (isset($f['group_key']) && Uuid::isValid($f['group_key'])) {
            $f['group_key'] = strtoupper(str_replace('-', '', $f['group_key']));
        }

        return [$store, $f];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
