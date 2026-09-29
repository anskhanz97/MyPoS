<?php

namespace App\Http\Resources;

use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Approval */
class ApprovalResource extends JsonResource
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
            'permission' => $this->permission_code->value,
            'target_type' => $this->target_type,
            'target_id' => $this->target_public_id,
            'reason' => $this->reason,
            'expires_at' => $this->expires_at->toAtomString(),
        ];
    }
}
