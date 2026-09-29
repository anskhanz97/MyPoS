<?php

namespace App\Http\Resources;

use App\Models\SettingsVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SettingsVersion */
class SettingsVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'scope' => $this->scope->value, 'store_id' => $this->store?->public_id, 'version' => $this->version, 'payload' => $this->payload, 'published_at' => $this->published_at->toAtomString(), 'effective_at' => $this->effective_at?->toAtomString()];
    }
}
