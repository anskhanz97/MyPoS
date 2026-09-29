<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Merchant */
class MerchantResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'store_data_mode' => $this->store_data_mode->value,
            'store_data_mode_locked_at' => $this->store_data_mode_locked_at?->toAtomString(),
            'status' => $this->status->value,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'stores' => StoreResource::collection($this->whenLoaded('stores')),
        ];
    }
}
