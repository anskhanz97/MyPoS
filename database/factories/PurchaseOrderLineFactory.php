<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrderLine> */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    public function definition(): array
    {
        return ['purchase_order_id' => PurchaseOrder::factory(), 'product_variant_id' => ProductVariant::factory(), 'purchase_unit_id' => UnitOfMeasure::factory(), 'product_name_snapshot' => fake()->words(3, true), 'unit_symbol_snapshot' => 'u', 'ordered_quantity_uom' => '1.000000', 'factor_to_base' => '1.000000', 'ordered_quantity_base' => '1.000000', 'expected_unit_cost' => '100.000000'];
    }
}
