<?php

namespace App\Domain\Customers;

use App\Models\OperationalCustomer;

final class CustomerSnapshotBuilder
{
    /** @return array{id: string, local_reference: string, name: string, phone: string|null, email: string|null} */
    public function build(OperationalCustomer $customer): array
    {
        return ['id' => $customer->public_id, 'local_reference' => $customer->local_reference, 'name' => $customer->name, 'phone' => $customer->normalized_phone, 'email' => $customer->normalized_email];
    }
}
