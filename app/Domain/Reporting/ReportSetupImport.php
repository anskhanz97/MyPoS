<?php

namespace App\Domain\Reporting;

use App\Domain\Catalog\CatalogScope;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

final class ReportSetupImport
{
    public const HEADERS = ['sku', 'category_code', 'reporting_category_code', 'brand_code', 'product_type_code', 'primary_supplier_id', 'size', 'miscellaneous', 'minimum_quantity', 'target_quantity'];

    public static function encodeCsvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return str_starts_with($value, "'") || preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    public static function decodeCsvCell(string $value): string
    {
        return str_starts_with($value, "''") || preg_match("/^'[\\s\\x00-\\x1F]*[=+\\-@]/u", $value) === 1 ? substr($value, 1) : $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{variant: ProductVariant, product: array<string, mixed>}
     */
    public function resolve(Merchant $merchant, Store $store, array $row): array
    {
        Validator::make($row, ['sku' => ['required', 'string', 'max:80'], 'size' => ['nullable', 'string', 'max:80'], 'miscellaneous' => ['required', 'in:yes,no,1,0'], 'primary_supplier_id' => ['nullable', 'uuid'], 'minimum_quantity' => ['nullable', 'numeric', 'min:0'], 'target_quantity' => ['nullable', 'numeric', 'gte:minimum_quantity']])->validate();
        $variant = ProductVariant::query()->where('merchant_id', $merchant->id)->where('store_id', app(CatalogScope::class)->storeId($merchant, $store))->where('sku', mb_strtoupper($row['sku']))->with('product')->first();
        if ($variant === null) {
            throw ValidationException::withMessages(['sku' => 'Unknown SKU in this catalogue.']);
        }
        $columns = ['category_code' => ['product_category_id', 'product_categories'], 'reporting_category_code' => ['reporting_category_id', 'reporting_categories'], 'brand_code' => ['product_brand_id', 'product_brands'], 'product_type_code' => ['product_reporting_type_id', 'product_reporting_types']];
        $product = ['is_miscellaneous' => in_array($row['miscellaneous'], ['yes', '1'], true)];
        foreach ($columns as $input => [$column, $table]) {
            $value = $row[$input] ?? '';
            $query = DB::table($table)->where('merchant_id', $merchant->id)->where('code', $value)->where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $store->id));
            $found = $value === '' ? null : ((clone $query)->where('id', $variant->product->getAttribute($column))->first() ?? $query->orderByDesc('store_id')->first());
            if ($value !== '' && ($found === null || ($found->archived_at !== null && $found->id !== $variant->product->getAttribute($column)))) {
                throw ValidationException::withMessages([$input => 'Unknown or archived classification code.']);
            }
            $product[$column] = $found?->id;
        }
        $supplier = empty($row['primary_supplier_id']) ? null : DB::table('suppliers')->where('merchant_id', $merchant->id)->where('public_id', Uuid::fromString($row['primary_supplier_id'])->getBytes())->first();
        if (! empty($row['primary_supplier_id']) && $supplier === null) {
            throw ValidationException::withMessages(['primary_supplier_id' => 'Unknown supplier in this merchant.']);
        }
        $product['primary_supplier_id'] = $supplier?->id;

        return compact('variant', 'product');
    }

    /** @param array<string, mixed> $row */
    public function commit(Merchant $merchant, Store $store, array $row): string
    {
        $resolved = $this->resolve($merchant, $store, $row);
        $variant = $resolved['variant'];
        $variant->product->forceFill($resolved['product'])->save();
        $variant->forceFill(['attributes' => array_replace($variant->attributes ?? [], ['size' => $row['size'] ?: null])])->save();
        if (($row['minimum_quantity'] ?? '') !== '') {
            DB::table('inventory_reorder_policies')->updateOrInsert(['store_id' => $store->id, 'product_variant_id' => $variant->id], ['merchant_id' => $merchant->id, 'minimum_quantity' => $row['minimum_quantity'], 'target_quantity' => $row['target_quantity'] ?: null, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $variant->public_id;
    }
}
