<?php

namespace App\Http\Resources;

use App\Models\MerchantSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MerchantSubscription */
class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'merchant_id' => $this->merchant->public_id,
            'status' => $this->status->value, 'starts_at' => $this->starts_at->toAtomString(),
            'trial_ends_at' => $this->trial_ends_at?->toAtomString(), 'current_period_ends_at' => $this->current_period_ends_at->toAtomString(),
            'grace_ends_at' => $this->grace_ends_at?->toAtomString(), 'version' => $this->version,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'capabilities' => ['new_operations' => in_array($this->status->value, ['trial', 'active', 'grace'], true), 'pending_offline_submission' => true, 'data_export' => true, 'support' => true],
        ];
    }
}
