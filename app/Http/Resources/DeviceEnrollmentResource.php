<?php

namespace App\Http\Resources;

use App\Models\DeviceEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeviceEnrollment */
class DeviceEnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'name' => $this->name, 'status' => $this->status->value,
            'credential_version' => $this->credential_version, 'fencing_epoch' => $this->fencing_epoch,
            'settings_version' => $this->settings_version, 'protocol_min' => $this->protocol_min, 'protocol_max' => $this->protocol_max,
            'enrolled_at' => $this->enrolled_at->toAtomString(), 'last_seen_at' => $this->last_seen_at?->toAtomString(),
            'lease_expires_at' => $this->lease_expires_at?->toAtomString(), 'revoked_at' => $this->revoked_at?->toAtomString(),
        ];
    }
}
