<?php

namespace App\Domain\Purchasing;

use App\Domain\Purchasing\Enums\SupplierCreditStatus;
use App\Models\OutboxEvent;
use App\Models\PurchaseReturn;
use App\Models\SecurityAuditEvent;
use App\Models\SupplierCredit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ConfirmSupplierCredit
{
    public function handle(SupplierCredit $credit, User $actor, string $supplierReference): SupplierCredit
    {
        return DB::transaction(function () use ($credit, $actor, $supplierReference): SupplierCredit {
            $locked = SupplierCredit::query()->with('purchaseReturn')->lockForUpdate()->findOrFail($credit->id);
            if ($locked->status === SupplierCreditStatus::Confirmed) {
                if ($locked->supplier_reference !== $supplierReference) {
                    throw new ConflictHttpException('This supplier credit was confirmed with a different reference.');
                }

                return $locked;
            }
            if ($locked->status !== SupplierCreditStatus::Expected) {
                throw new ConflictHttpException('Only an expected supplier credit can be confirmed.');
            }
            $locked->update(['status' => SupplierCreditStatus::Confirmed, 'supplier_reference' => $supplierReference]);
            PurchaseReturn::query()->whereKey($locked->purchase_return_id)->update(['credit_status' => SupplierCreditStatus::Confirmed, 'supplier_credit_reference' => $supplierReference]);
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'purchasing.supplier_credit.confirmed', 'aggregate_type' => 'supplier_credit', 'aggregate_id' => $locked->public_id, 'payload' => ['supplier_credit_id' => $locked->public_id, 'purchase_return_id' => $locked->purchaseReturn->public_id], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'purchasing.supplier_credit.confirmed', 'outcome' => 'succeeded', 'metadata' => ['supplier_credit_id' => $locked->public_id, 'purchase_return_id' => $locked->purchaseReturn->public_id], 'occurred_at' => now()]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
