<?php

namespace App\Http\Resources;

use App\Models\MerchantMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MerchantMembership */
class MerchantMembershipResource extends JsonResource
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
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'stores' => StoreResource::collection(
                $this->whenLoaded('storeAssignments', fn () => $this->storeAssignments->pluck('store')),
            ),
        ];
    }
}
