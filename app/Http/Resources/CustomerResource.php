<?php

namespace App\Http\Resources;

use App\Models\OperationalCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OperationalCustomer */
class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->public_id, 'scope' => $this->store_id === null ? 'merchant' : 'store', 'store_id' => $this->store?->public_id,
            'local_reference' => $this->local_reference, 'name' => $this->name, 'normalized_phone' => $this->normalized_phone, 'email' => $this->email,
            'email_receipt_preference' => $this->email_receipt_preference,
            'customer_group' => $this->customerGroup === null ? null : ['id' => $this->customerGroup->public_id, 'code' => $this->customerGroup->code, 'name' => $this->customerGroup->name],
            'loyalty_member_id' => $this->loyaltyLink?->loyaltyMember?->public_id,
            'archived_at' => $this->archived_at?->toAtomString(), 'version' => $this->version,
        ];
        if ($request->attributes->getBoolean('customer_minimal')) {
            return $base;
        }

        return $base + ['phone' => $this->phone, 'address_line' => $this->address_line, 'city' => $this->city, 'postal_code' => $this->postal_code, 'marketing_email_consent' => $this->marketing_email_consent, 'marketing_consent_at' => $this->marketing_consent_at?->toAtomString(), 'marketing_consent_source' => $this->marketing_consent_source];
    }
}
