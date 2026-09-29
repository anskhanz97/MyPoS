<?php

namespace App\Http\Resources;

use App\Models\InventoryLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryLocation */
class InventoryLocationResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'disposition' => $this->disposition->value,
            'is_default' => $this->is_default,
            'archived_at' => $this->archived_at?->toAtomString(),
        ];
    }
}
