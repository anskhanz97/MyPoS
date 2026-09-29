<?php

namespace App\Domain\DataTransfer;

use App\Domain\Catalog\CatalogScope;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Customers\CustomerWriter;
use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Models\DataTransferJob;
use App\Models\DataTransferRow;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\OperationalCustomer;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStoreAvailability;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ImportService
{
    private const HEADERS = [
        'catalog' => ['sku', 'name', 'uom_code', 'product_type', 'batch_tracking'],
        'customers' => ['local_reference', 'name', 'phone', 'email'],
        'opening_stock' => ['sku', 'location_code', 'quantity', 'unit_cost', 'batch_code', 'expiry_date'],
    ];

    public function __construct(
        private readonly CatalogScope $catalogScope,
        private readonly CustomerWriter $customers,
        private readonly PostInventoryValuation $valuation,
        private readonly PostStockMovement $stock,
    ) {}

    public function upload(User $actor, Merchant $merchant, Store $store, string $resource, UploadedFile $file): DataTransferJob
    {
        abort_unless(array_key_exists($resource, self::HEADERS), 422, 'Unsupported import resource.');
        abort_unless($file->isValid() && strtolower((string) $file->getClientOriginalExtension()) === 'csv', 422, 'Upload a valid UTF-8 CSV file.');
        abort_if(($file->getSize() ?: 0) > 5 * 1024 * 1024, 422, 'Import files cannot exceed 5 MB.');
        $content = file_get_contents($file->getRealPath());
        abort_unless(is_string($content) && mb_check_encoding($content, 'UTF-8'), 422, 'The CSV must be valid UTF-8.');
        $hash = hash('sha256', $content);
        $existing = DataTransferJob::query()->where('merchant_id', $merchant->id)->where('resource', $resource)->where('source_hash', $hash)->first();
        if ($existing instanceof DataTransferJob) {
            return $existing->load('rows');
        }
        $path = "merchants/{$merchant->public_id}/imports/{$hash}.csv";
        Storage::disk('local')->put($path, $content);

        return DB::transaction(function () use ($actor, $merchant, $store, $resource, $file, $hash, $path): DataTransferJob {
            $job = DataTransferJob::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id,
                'direction' => 'import', 'resource' => $resource, 'state' => 'validating', 'schema_version' => 1,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'disk' => 'local', 'source_path' => $path, 'source_hash' => $hash,
            ]);
            $this->validateCsv($job, $merchant, $store);

            return $job->refresh()->load('rows');
        });
    }

    public function commit(DataTransferJob $job, User $actor, Merchant $merchant, Store $store, string $commitKey): DataTransferJob
    {
        abort_unless($job->merchant_id === $merchant->id && $job->store_id === $store->id && $job->direction === 'import', 404);
        if ($job->state === 'committed') {
            if ($job->commit_key === $commitKey) {
                return $job->load('rows');
            }
            throw new ConflictHttpException('This import was already committed with another key.');
        }
        abort_unless($job->state === 'validated' && $job->invalid_rows === 0, 409, 'Resolve every validation error before commit.');

        return DB::transaction(function () use ($job, $actor, $merchant, $store, $commitKey): DataTransferJob {
            $locked = DataTransferJob::query()->lockForUpdate()->findOrFail($job->id);
            if ($locked->state === 'committed') {
                abort_unless($locked->commit_key === $commitKey, 409, 'This import was already committed with another key.');

                return $locked->load('rows');
            }
            $locked->update(['state' => 'committing', 'commit_key' => $commitKey]);
            foreach ($locked->rows()->orderBy('row_number')->get() as $row) {
                $payload = $row->payloadData();
                $reference = match ($locked->resource) {
                    'catalog' => $this->commitCatalog($merchant, $store, $payload),
                    'customers' => $this->commitCustomer($actor, $merchant, $store, $payload),
                    'opening_stock' => $this->commitOpeningStock($actor, $merchant, $store, $locked, $row->row_number, $payload),
                    default => abort(422, 'Unsupported import resource.'),
                };
                $row->update(['state' => 'committed', 'result_reference' => $reference]);
            }
            $locked->update(['state' => 'committed', 'committed_at' => now()]);

            return $locked->refresh()->load('rows');
        }, attempts: 3);
    }

    private function validateCsv(DataTransferJob $job, Merchant $merchant, Store $store): void
    {
        $path = Storage::disk($job->disk)->path((string) $job->source_path);
        $handle = fopen($path, 'rb');
        abort_unless(is_resource($handle), 422, 'The uploaded CSV cannot be read.');
        $headers = fgetcsv($handle);
        abort_unless(is_array($headers), 422, 'The CSV has no header row.');
        $headers = array_map(fn (mixed $v): string => trim((string) $v), $headers);
        abort_if(array_intersect($headers, ['customer_balance', 'credit_balance', 'debt']) !== [], 422, 'Customer debt or credit balances are outside launch scope.');
        abort_unless($headers === self::HEADERS[$job->resource], 422, 'CSV headers do not match schema version 1.');
        $seen = [];
        $total = $valid = $invalid = 0;
        while (($values = fgetcsv($handle)) !== false) {
            $total++;
            abort_if($total > 2000, 422, 'Import files cannot exceed 2,000 data rows.');
            $values = array_pad($values, count($headers), '');
            $payload = [];
            foreach ($headers as $index => $header) {
                $payload[$header] = trim((string) $values[$index]);
            }
            $errors = $this->rowErrors($job->resource, $payload, $merchant, $store, $seen);
            $isValid = $errors === [];
            $isValid ? $valid++ : $invalid++;
            DataTransferRow::query()->create(['data_transfer_job_id' => $job->id, 'row_number' => $total + 1, 'payload' => $payload, 'errors' => $errors ?: null, 'state' => $isValid ? 'valid' : 'invalid']);
        }
        fclose($handle);
        abort_if($total === 0, 422, 'The CSV contains no data rows.');
        $job->update(['state' => $invalid === 0 ? 'validated' : 'validation_failed', 'total_rows' => $total, 'valid_rows' => $valid, 'invalid_rows' => $invalid]);
    }

    /**
     * @param  array<string,string>  $row
     * @param  array<string,bool>  $seen
     * @return list<string>
     */
    private function rowErrors(string $resource, array $row, Merchant $merchant, Store $store, array &$seen): array
    {
        $errors = [];
        $key = match ($resource) {
            'catalog' => mb_strtoupper($row['sku']),
            'opening_stock' => mb_strtoupper($row['sku'].'|'.$row['location_code'].'|'.$row['batch_code']),
            default => mb_strtoupper($row['local_reference']),
        };
        if ($key === '') {
            $errors[] = $resource === 'customers' ? 'local_reference is required' : 'sku is required';
        }
        if (isset($seen[$key])) {
            $errors[] = 'duplicate key in file';
        }
        $seen[$key] = true;
        if ($resource === 'catalog') {
            if ($row['name'] === '') {
                $errors[] = 'name is required';
            }
            if (! in_array($row['product_type'], array_column(ProductType::cases(), 'value'), true)) {
                $errors[] = 'invalid product_type';
            }
            if (! in_array(strtolower($row['batch_tracking']), ['yes', 'no', '1', '0'], true)) {
                $errors[] = 'batch_tracking must be yes or no';
            }
            if (! UnitOfMeasure::query()->where('merchant_id', $merchant->id)->where('code', mb_strtoupper($row['uom_code']))->exists()) {
                $errors[] = 'unknown uom_code';
            }
            if ($this->variantQuery($merchant, $store, $key)->exists()) {
                $errors[] = 'sku already exists';
            }
        } elseif ($resource === 'customers') {
            if ($row['name'] === '') {
                $errors[] = 'name is required';
            }
            if ($row['email'] !== '' && filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'invalid email';
            }
            if (OperationalCustomer::query()->where('merchant_id', $merchant->id)->where('local_reference', $key)->exists()) {
                $errors[] = 'local_reference already exists';
            }
        } else {
            $variant = $this->variantQuery($merchant, $store, mb_strtoupper($row['sku']))->with('product')->first();
            if (! $variant instanceof ProductVariant) {
                $errors[] = 'unknown sku';
            }
            if (! InventoryLocation::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('code', mb_strtoupper($row['location_code']))->exists()) {
                $errors[] = 'unknown location_code';
            }
            if (! is_numeric($row['quantity']) || (float) $row['quantity'] <= 0) {
                $errors[] = 'quantity must be positive';
            }
            if (! is_numeric($row['unit_cost']) || (float) $row['unit_cost'] < 0) {
                $errors[] = 'unit_cost must be zero or positive';
            }
            if ($variant instanceof ProductVariant && $variant->product->batch_tracking_enabled && ($row['batch_code'] === '' || ! $this->date($row['expiry_date']))) {
                $errors[] = 'tracked stock requires batch_code and valid expiry_date';
            }
            if ($variant instanceof ProductVariant && ! $variant->product->batch_tracking_enabled && ($row['batch_code'] !== '' || $row['expiry_date'] !== '')) {
                $errors[] = 'untracked stock cannot include batch data';
            }
        }

        return $errors;
    }

    /** @param array<string,mixed> $row */
    private function commitCatalog(Merchant $merchant, Store $store, array $row): string
    {
        $scopeStore = $this->catalogScope->storeId($merchant, $store);
        $product = Product::query()->create(['merchant_id' => $merchant->id, 'store_id' => $scopeStore, 'type' => $row['product_type'], 'name' => $row['name'], 'batch_tracking_enabled' => in_array(strtolower((string) $row['batch_tracking']), ['yes', '1'], true), 'structure_version' => 1]);
        $unit = UnitOfMeasure::query()->where('merchant_id', $merchant->id)->where('code', mb_strtoupper((string) $row['uom_code']))->firstOrFail();
        $variant = ProductVariant::query()->create(['merchant_id' => $merchant->id, 'store_id' => $scopeStore, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => mb_strtoupper((string) $row['sku']), 'name' => $row['name'], 'is_weighted' => $row['product_type'] === ProductType::Weighted->value, 'is_default' => true]);
        if ($scopeStore === null) {
            ProductStoreAvailability::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'product_id' => $product->id, 'is_available' => true]);
        }

        return $variant->public_id;
    }

    /** @param array<string,mixed> $row */
    private function commitCustomer(User $actor, Merchant $merchant, Store $store, array $row): string
    {
        return $this->customers->create($actor, $merchant, $store, ['local_reference' => mb_strtoupper((string) $row['local_reference']), 'name' => $row['name'], 'phone' => $row['phone'] ?: null, 'email' => $row['email'] ?: null])->public_id;
    }

    /** @param array<string,mixed> $row */
    private function commitOpeningStock(User $actor, Merchant $merchant, Store $store, DataTransferJob $job, int $rowNumber, array $row): string
    {
        $variant = $this->variantQuery($merchant, $store, mb_strtoupper((string) $row['sku']))->with('product')->firstOrFail();
        $location = InventoryLocation::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('code', mb_strtoupper((string) $row['location_code']))->firstOrFail();
        $batch = null;
        if ((string) $row['batch_code'] !== '') {
            $batch = ProductBatch::query()->firstOrCreate(['merchant_id' => $merchant->id, 'product_variant_id' => $variant->id, 'code' => mb_strtoupper((string) $row['batch_code'])], ['origin_store_id' => $store->id, 'expiry_date' => $row['expiry_date'], 'received_on' => now()->toDateString(), 'supplier_reference' => 'OPENING-'.$job->public_id]);
        }
        $reference = 'IMPORT-'.$job->public_id;
        $result = $this->valuation->inbound($store, $variant, InventoryCostEntryKind::AdjustmentIn, (string) $row['quantity'], (string) $row['unit_cost'], 'opening_import', $reference, (string) $rowNumber, now()->toAtomString(), function (string $unitCost, InventoryCostState $state) use ($location, $variant, $row, $batch, $actor, $reference, $rowNumber) {
            return $this->stock->handle(new StockMovementCommand($location, $variant, InventoryMovementKind::OpeningBalance, (string) $row['quantity'], 'opening_import', $reference, (string) $rowNumber, 'stock', $batch, $actor, reasonText: 'Validated opening stock import', unitCost: $unitCost, costState: $state))['movement'];
        });

        return $result['movement']->public_id;
    }

    /** @return Builder<ProductVariant> */
    private function variantQuery(Merchant $merchant, Store $store, string $sku): Builder
    {
        return ProductVariant::query()->where('merchant_id', $merchant->id)->where('store_id', $this->catalogScope->storeId($merchant, $store))->where('sku', $sku);
    }

    private function date(string $value): bool
    {
        try {
            CarbonImmutable::createFromFormat('Y-m-d', $value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
