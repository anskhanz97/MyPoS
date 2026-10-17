<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\DataTransfer\ExportService;
use App\Domain\DataTransfer\ImportService;
use App\Domain\Reporting\ReportAccess;
use App\Domain\Reporting\ReportSetupImport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessDataExport;
use App\Models\DataTransferJob;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataTransferController extends Controller
{
    public function imports(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $auth, ImportService $service): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($store->merchant_id === $merchant->id, 404);
        $data = $request->validate(['resource' => ['required', Rule::in(['catalog', 'customers', 'opening_stock', 'reporting_catalog'])], 'file' => ['required', 'file', 'max:5120']]);
        $auth->authorize($user, $merchant, $this->importPermission($data['resource']), $store);
        $job = $service->upload($user, $merchant, $store, $data['resource'], $data['file']);

        return response()->json(['data' => $job], 201);
    }

    public function commit(Request $request, Merchant $merchant, Store $store, DataTransferJob $dataTransferJob, RoleAuthorizer $auth, ImportService $service): JsonResponse
    {
        $user = $this->user($request);
        $auth->authorize($user, $merchant, $this->importPermission($dataTransferJob->resource), $store);
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[0-9a-f-]{36}$/i', $key) === 1, 422, 'A UUID Idempotency-Key is required.');

        return response()->json(['data' => $service->commit($dataTransferJob, $user, $merchant, $store, $key)]);
    }

    public function export(Request $request, Merchant $merchant, RoleAuthorizer $auth): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['resource' => ['required', Rule::in(ExportService::RESOURCES)], 'store_id' => ['nullable', 'string'], 'report_key' => ['required_if:resource,report', Rule::in(['sales', 'inventory', 'purchasing', 'expenses', 'loyalty', 'workforce', 'cash'])], 'start' => ['required_if:resource,report', 'date'], 'end' => ['required_if:resource,report', 'date', 'after_or_equal:start']]);
        $store = isset($data['store_id']) ? (new Store)->resolveRouteBinding($data['store_id']) : null;
        abort_unless($store === null || $store instanceof Store, 404);
        abort_unless($store === null || $store->merchant_id === $merchant->id, 404);
        $membership = $auth->authorize($user, $merchant, $store === null ? Permission::ReportConsolidated : Permission::ReportStore, $store);
        abort_if($membership->role->value === 'cashier', 403, 'Data exports require a manager or owner.');
        if ($data['resource'] === 'customers') {
            $auth->authorize($user, $merchant, Permission::CustomerExport, $store);
        }
        if ($data['resource'] === 'payroll' || ($data['resource'] === 'report' && ($data['report_key'] ?? null) === 'workforce')) {
            $auth->authorize($user, $merchant, Permission::PayrollManage);
        }
        $job = DataTransferJob::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store?->id, 'actor_user_id' => $user->id, 'direction' => 'export', 'resource' => $data['resource'], 'state' => 'queued', 'schema_version' => 1, 'disk' => 'local', 'options' => array_filter(['report_key' => $data['report_key'] ?? null, 'start' => $data['start'] ?? null, 'end' => $data['end'] ?? null])]);
        ProcessDataExport::dispatch($job->id);

        return response()->json(['data' => $job], 202);
    }

    public function show(Request $request, Merchant $merchant, DataTransferJob $dataTransferJob, RoleAuthorizer $auth): JsonResponse
    {
        $this->authorizeJob($request, $merchant, $dataTransferJob, $auth);

        return response()->json(['data' => $dataTransferJob->load('rows')]);
    }

    public function download(Request $request, Merchant $merchant, DataTransferJob $dataTransferJob, RoleAuthorizer $auth): StreamedResponse
    {
        $this->authorizeJob($request, $merchant, $dataTransferJob, $auth);
        abort_unless($dataTransferJob->state === 'ready' && $dataTransferJob->artifact_path !== null && $dataTransferJob->artifactIsCurrent(), 404);
        abort_unless(Storage::disk($dataTransferJob->disk)->exists($dataTransferJob->artifact_path), 404);

        return Storage::disk($dataTransferJob->disk)->download($dataTransferJob->artifact_path, $dataTransferJob->resource.'-export.csv', ['Cache-Control' => 'private, no-store', 'Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(Request $request, Merchant $merchant, Store $store, string $resource, RoleAuthorizer $auth): StreamedResponse
    {
        $auth->authorize($this->user($request), $merchant, $this->importPermission($resource), $store);
        $headers = match ($resource) {
            'reporting_catalog' => ReportSetupImport::HEADERS,
            'catalog' => ['sku', 'name', 'uom_code', 'product_type', 'batch_tracking'], 'customers' => ['local_reference', 'name', 'phone', 'email'], 'opening_stock' => ['sku', 'location_code', 'quantity', 'unit_cost', 'batch_code', 'expiry_date'], default => abort(404)
        };

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'wb');
            abort_unless(is_resource($out), 500);
            fputcsv($out, $headers);
            fclose($out);
        }, $resource.'-v1-template.csv', ['Cache-Control' => 'private, no-store']);
    }

    private function authorizeJob(Request $request, Merchant $merchant, DataTransferJob $job, RoleAuthorizer $auth): void
    {
        abort_unless($job->merchant_id === $merchant->id && $job->actor_user_id === $this->user($request)->id, 404);
        $store = $job->store_id === null ? null : Store::query()->findOrFail($job->store_id);
        $membership = $auth->authorize($this->user($request), $merchant, $store === null ? Permission::ReportConsolidated : Permission::ReportStore, $store);
        abort_if($membership->role->value === 'cashier' && $job->direction === 'export', 403, 'Data exports require a manager or owner.');
        if ($job->resource === 'customers' && $job->direction === 'export') {
            $auth->authorize($this->user($request), $merchant, Permission::CustomerExport, $store);
        }
        if ($job->resource === 'report_workspace') {
            app(ReportAccess::class)->authorize($this->user($request), $merchant, $store, $job->optionsData()['report_key'], true);
        }
        if ($job->resource === 'report' && ($job->optionsData()['report_key'] ?? null) === 'sales') {
            $auth->authorize($this->user($request), $merchant, Permission::ReportCosts, $store);
        }
        if ($job->resource === 'payroll' || ($job->resource === 'report' && ($job->optionsData()['report_key'] ?? null) === 'workforce')) {
            $auth->authorize($this->user($request), $merchant, Permission::PayrollManage);
        }
    }

    private function importPermission(string $resource): Permission
    {
        return match ($resource) {
            'catalog', 'reporting_catalog' => Permission::CatalogManage, 'customers' => Permission::CustomerManage, 'opening_stock' => Permission::InventoryManage, default => abort(404)
        };
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
