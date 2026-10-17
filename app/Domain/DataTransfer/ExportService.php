<?php

namespace App\Domain\DataTransfer;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Reporting\StandardReportService;
use App\Models\DataTransferJob;
use App\Models\Expense;
use App\Models\Merchant;
use App\Models\OperationalCustomer;
use App\Models\PayrollItem;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ExportService
{
    public const RESOURCES = ['catalog', 'customers', 'expenses', 'payroll', 'report'];

    public function __construct(private readonly RoleAuthorizer $authorizer, private readonly StandardReportService $reports) {}

    public function execute(DataTransferJob $job): void
    {
        try {
            $job->update(['state' => 'processing', 'failure_message' => null]);
            $merchant = Merchant::query()->findOrFail($job->merchant_id);
            $actor = User::query()->findOrFail($job->actor_user_id);
            $store = $job->store_id === null ? null : Store::query()->findOrFail($job->store_id);
            $membership = $this->authorizer->authorize($actor, $merchant, $store === null ? Permission::ReportConsolidated : Permission::ReportStore, $store);
            abort_if($membership->role->value === 'cashier', 403, 'Data exports require a manager or owner.');
            if ($job->resource === 'customers') {
                $this->authorizer->authorize($actor, $merchant, Permission::CustomerExport, $store);
            }
            if ($job->resource === 'report' && ($job->optionsData()['report_key'] ?? null) === 'sales') {
                $this->authorizer->authorize($actor, $merchant, Permission::ReportCosts, $store);
            }
            if ($job->resource === 'payroll' || ($job->resource === 'report' && ($job->optionsData()['report_key'] ?? null) === 'workforce')) {
                $this->authorizer->authorize($actor, $merchant, Permission::PayrollManage);
            }
            [$headers, $rows] = $this->rows($job, $merchant, $store);
            $stream = fopen('php://temp', 'w+b');
            abort_unless(is_resource($stream), 500);
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, array_map($this->cell(...), $row));
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);
            abort_unless(is_string($content), 500);
            $path = "merchants/{$merchant->public_id}/exports/{$job->public_id}.csv";
            Storage::disk($job->disk)->put($path, $content);
            $job->update(['state' => 'ready', 'artifact_path' => $path, 'artifact_hash' => hash('sha256', $content), 'total_rows' => count($rows), 'expires_at' => now()->addDays(7)]);
        } catch (Throwable $e) {
            $job->update(['state' => 'failed', 'failure_message' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }

    /** @return array{0:list<string>,1:list<list<mixed>>} */
    private function rows(DataTransferJob $job, Merchant $merchant, ?Store $store): array
    {
        if ($job->resource === 'catalog') {
            $scopeStoreId = $merchant->store_data_mode->value === 'independent' ? $store?->id : null;
            $rows = [];
            foreach (ProductVariant::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->with(['product', 'baseUnit'])->orderBy('sku')->limit(10000)->get() as $variant) {
                $rows[] = [$variant->sku, $variant->name, $variant->product->type->value, $variant->baseUnit->code, $variant->product->batch_tracking_enabled ? 'yes' : 'no'];
            }

            return [['sku', 'name', 'product_type', 'uom_code', 'batch_tracking'], $rows];
        }
        if ($job->resource === 'customers') {
            $scopeStoreId = $merchant->store_data_mode->value === 'independent' ? $store?->id : null;
            $rows = [];
            foreach (OperationalCustomer::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->orderBy('local_reference')->limit(10000)->get() as $customer) {
                $rows[] = [$customer->local_reference, $customer->name, $customer->phone, $customer->email];
            }

            return [['local_reference', 'name', 'phone', 'email'], $rows];
        }
        if ($job->resource === 'expenses') {
            $rows = [];
            foreach ($this->scoped(Expense::query(), $merchant->id, $store?->id)->orderByDesc('expense_date')->limit(10000)->get() as $expense) {
                $rows[] = [CarbonImmutable::parse($expense->expense_date)->toDateString(), $expense->payee, $expense->amount, $expense->payment_source, $expense->reference, $expense->reason];
            }

            return [['date', 'payee', 'amount', 'payment_source', 'reference', 'reason'], $rows];
        }
        if ($job->resource === 'payroll') {
            $rows = [];
            foreach (PayrollItem::query()->where('merchant_id', $merchant->id)->when($store !== null, fn (Builder $q) => $q->whereHas('staffProfile', fn (Builder $s) => $s->where('default_store_id', $store->id)))->with(['run', 'staffProfile'])->limit(10000)->get() as $item) {
                $rows[] = [$item->run->period_end->toDateString(), $item->staffProfile->employee_number, $item->gross_amount, $item->deduction_amount, $item->net_amount, $item->paid_amount];
            }

            return [['period_end', 'employee_number', 'gross_amount', 'deduction_amount', 'net_amount', 'paid_amount'], $rows];
        }
        abort_unless($job->resource === 'report', 422, 'Unsupported export resource.');

        return $this->reportRows($job, $merchant, $store);
    }

    /** @return array{0:list<string>,1:list<list<mixed>>} */
    private function reportRows(DataTransferJob $job, Merchant $merchant, ?Store $store): array
    {
        $options = $job->optionsData();
        $result = $this->reports->run($merchant, $store?->id, (string) ($options['report_key'] ?? 'sales'), CarbonImmutable::parse((string) ($options['start'] ?? now()->startOfMonth()->toDateString())), CarbonImmutable::parse((string) ($options['end'] ?? now()->toDateString())));
        $rows = [];
        foreach ($result['data'] as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $rows[] = [$key, $value];
            }
        }

        return [['metric', 'value'], $rows];
    }

    private function cell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/u', $cell) === 1 ? "'".$cell : $cell;
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function scoped(Builder $query, int $merchantId, ?int $storeId): Builder
    {
        return $query->where('merchant_id', $merchantId)->when($storeId !== null, fn (Builder $inner) => $inner->where('store_id', $storeId));
    }
}
