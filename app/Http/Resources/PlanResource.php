<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'code' => $this->code, 'name' => $this->name,
            'billing_period_days' => $this->billing_period_days, 'grace_days' => $this->grace_days,
            'price' => $this->price, 'currency' => $this->currency, 'is_active' => $this->is_active,
            'entitlements' => $this->whenLoaded('entitlements', fn () => $this->entitlements->map(fn ($item) => ['code' => $item->code, 'enabled' => $item->enabled, 'limit_value' => $item->limit_value])->values()),
        ];
    }
}
