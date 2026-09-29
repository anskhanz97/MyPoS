<?php

namespace App\Domain\Sales;

use App\Models\OutboxEvent;
use App\Models\SaleReturn;
use App\Models\SaleReturnTender;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ConfirmExternalReturnRefund
{
    /** @return array{tender: SaleReturnTender, return: SaleReturn, replayed: bool} */
    public function handle(SaleReturn $return, SaleReturnTender $tender, User $actor, string $reference, string $source): array
    {
        return DB::transaction(function () use ($return, $tender, $actor, $reference, $source): array {
            $lockedReturn = SaleReturn::query()->lockForUpdate()->findOrFail($return->id);
            $lockedTender = SaleReturnTender::query()->lockForUpdate()->findOrFail($tender->id);
            abort_unless($lockedTender->sale_return_id === $lockedReturn->id && $lockedTender->type !== 'cash', 404);
            if ($lockedTender->state === 'refunded') {
                if ($lockedTender->external_reference !== $reference || $lockedTender->confirmation_source !== $source) {
                    throw new ConflictHttpException('This external refund was already confirmed with different evidence.');
                }

                return ['tender' => $lockedTender, 'return' => $lockedReturn, 'replayed' => true];
            }
            abort_unless($lockedTender->state === 'pending_external_action', 409, 'This refund tender is not awaiting external action.');
            $lockedTender->update(['state' => 'refunded', 'external_reference' => $reference, 'confirmation_source' => $source, 'confirmed_by_user_id' => $actor->id, 'confirmed_at' => now()]);
            if (! SaleReturnTender::query()->where('sale_return_id', $lockedReturn->id)->where('state', 'pending_external_action')->exists()) {
                $payload = $lockedReturn->receipt_payload;
                $payload['state'] = 'posted';
                foreach ($payload['tenders'] ?? [] as &$row) {
                    if (($row['type'] ?? null) === $lockedTender->type && ($row['amount'] ?? null) === $lockedTender->amount && ($row['state'] ?? null) === 'pending_external_action') {
                        $row['state'] = 'refunded';
                        $row['external_reference'] = $reference;
                        break;
                    }
                }
                unset($row);
                $lockedReturn->update(['state' => 'posted', 'receipt_payload' => $payload]);
            }
            $now = now();
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $lockedReturn->merchant_id, 'store_id' => $lockedReturn->store_id, 'event_type' => 'sales.return.refund_confirmed', 'aggregate_type' => 'sale_return', 'aggregate_id' => $lockedReturn->public_id, 'payload' => ['return_id' => $lockedReturn->public_id, 'tender_id' => $lockedTender->public_id, 'state' => $lockedReturn->state], 'occurred_at' => $now, 'available_at' => $now]);
            SecurityAuditEvent::query()->create(['merchant_id' => $lockedReturn->merchant_id, 'store_id' => $lockedReturn->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'sales.return.refund_confirmed', 'outcome' => 'succeeded', 'metadata' => ['return_id' => $lockedReturn->public_id, 'tender_id' => $lockedTender->public_id], 'occurred_at' => $now]);

            return ['tender' => $lockedTender->refresh(), 'return' => $lockedReturn->refresh(), 'replayed' => false];
        });
    }
}
