<?php

namespace App\Http\Resources;

use App\Models\Register;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Register */
class RegisterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'name' => $this->name, 'code' => $this->code, 'status' => $this->status, 'capabilities' => $this->capabilities ?? [], 'fencing_epoch' => $this->fencing_epoch, 'devices' => DeviceEnrollmentResource::collection($this->whenLoaded('devices'))];
    }
}
