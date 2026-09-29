<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'kind' => $this->movement_kind->value,
            'quantity_base' => $this->quantity_base,
            'unit_cost' => $this->unit_cost,
            'cost_state' => $this->cost_state->value,
            'occurred_at' => $this->occurred_at->toAtomString(),
            'location' => ['id' => $this->location->public_id, 'code' => $this->location->code, 'name' => $this->location->name, 'disposition' => $this->location->disposition->value],
            'variant' => ['id' => $this->variant->public_id, 'sku' => $this->variant->sku, 'name' => $this->variant->name, 'product_name' => $this->variant->product->name, 'base_unit' => $this->variant->baseUnit->symbol],
            'batch' => $this->batch === null ? null : ['id' => $this->batch->public_id, 'code' => $this->batch->code, 'expiry_date' => $this->batch->expiry_date->toDateString()],
            'source' => ['type' => $this->source_type, 'reference' => $this->source_reference, 'line_reference' => $this->source_line_reference, 'effect' => $this->source_effect],
            'adjustment_reason' => $this->adjustmentReason === null ? null : ['id' => $this->adjustmentReason->public_id, 'code' => $this->adjustmentReason->code, 'name' => $this->adjustmentReason->name],
            'reason_text' => $this->reason_text,
            'reverses_movement_id' => $this->reversedMovement?->public_id,
        ];
    }
}
