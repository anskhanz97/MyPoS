<?php

namespace App\Http\Requests;

use App\Domain\Catalog\Enums\BarcodeKind;
use App\Domain\Catalog\Enums\ProductType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ProductType::class)], 'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'], 'tax_classification_reference' => ['nullable', 'string', 'max:80'],
            'batch_tracking_enabled' => ['sometimes', 'boolean'], 'available_store_ids' => ['sometimes', 'array'], 'available_store_ids.*' => ['uuid', 'distinct'],
            'variants' => ['required', 'array', 'min:1', 'max:200'],
            'variants.*.name' => ['required', 'string', 'max:160'], 'variants.*.sku' => ['required', 'string', 'max:80'],
            'variants.*.base_unit_id' => ['required', 'uuid'], 'variants.*.attributes' => ['sometimes', 'array'], 'variants.*.attributes.*' => ['string', 'max:120'],
            'variants.*.is_weighted' => ['sometimes', 'boolean'], 'variants.*.is_default' => ['sometimes', 'boolean'],
            'variants.*.barcodes' => ['sometimes', 'array', 'max:20'], 'variants.*.barcodes.*.code' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Za-z-]+$/'],
            'variants.*.barcodes.*.kind' => ['required', Rule::enum(BarcodeKind::class)], 'variants.*.barcodes.*.is_primary' => ['sometimes', 'boolean'],
            'variants.*.conversions' => ['sometimes', 'array', 'max:30'], 'variants.*.conversions.*.unit_id' => ['required', 'uuid'],
            'variants.*.conversions.*.factor_to_base' => ['required', 'decimal:1,6', 'gt:0'],
            'variants.*.bundle_components' => ['sometimes', 'array', 'max:100'], 'variants.*.bundle_components.*.variant_id' => ['required', 'uuid'],
            'variants.*.bundle_components.*.quantity_base' => ['required', 'decimal:1,6', 'gt:0'],
            'extra_groups' => ['sometimes', 'array', 'max:30'], 'extra_groups.*.name' => ['required', 'string', 'max:120'],
            'extra_groups.*.minimum_selected' => ['required', 'integer', 'min:0', 'max:100'], 'extra_groups.*.maximum_selected' => ['required', 'integer', 'min:1', 'max:100'],
            'extra_groups.*.options' => ['required', 'array', 'min:1', 'max:100'], 'extra_groups.*.options.*.name' => ['required', 'string', 'max:120'],
            'extra_groups.*.options.*.price_delta' => ['required', 'decimal:0,2'], 'extra_groups.*.options.*.stock_variant_id' => ['nullable', 'uuid'],
            'extra_groups.*.options.*.stock_quantity_base' => ['nullable', 'decimal:1,6', 'gt:0'],
        ];
    }
}
