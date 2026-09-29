<?php

namespace App\Http\Resources;

use App\Models\SupportAccessGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupportAccessGrant */
class SupportAccessGrantResource extends JsonResource
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
            'operator_id' => $this->operator->public_id, 'scope' => $this->scope,
            'purpose' => $this->purpose, 'expires_at' => $this->expires_at->toAtomString(),
            'revoked_at' => $this->revoked_at?->toAtomString(),
        ];
    }
}
