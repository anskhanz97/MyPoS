<?php

namespace Database\Factories;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return ['merchant_id' => fn (array $attributes) => Store::query()->find($attributes['store_id'])?->merchant_id, 'store_id' => Store::factory(), 'supplier_id' => Supplier::factory(), 'number' => 'PO-'.fake()->unique()->numerify('######'), 'state' => PurchaseOrderState::Draft, 'order_date' => now()->toDateString()];
    }
}
