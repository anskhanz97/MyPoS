<?php

namespace App\Domain\Purchasing;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IssuePurchaseOrder
{
    public function __construct(private readonly PurchaseOrderRecorder $recorder) {}

    /** @return array{order: PurchaseOrder, replayed: bool} */
    public function handle(PurchaseOrder $order, User $actor, string $key, int $expectedVersion): array
    {
        return DB::transaction(function () use ($order, $actor, $key, $expectedVersion): array {
            $locked = PurchaseOrder::query()->with(['supplier', 'store', 'lines.variant.product', 'lines.purchaseUnit'])->lockForUpdate()->findOrFail($order->id);
            $hash = hash('sha256', json_encode(['order' => $locked->public_id, 'expected_version' => $expectedVersion], JSON_THROW_ON_ERROR));
            if ($locked->issue_key !== null) {
                if ($locked->issue_key === $key && hash_equals((string) $locked->issue_hash, $hash)) {
                    return ['order' => $locked->load('history'), 'replayed' => true];
                }
                throw new ConflictHttpException('This purchase order was already issued with different command evidence.');
            }
            if ($locked->state !== PurchaseOrderState::Draft || $locked->version !== $expectedVersion) {
                throw new ConflictHttpException('Only the current draft purchase order can be issued.');
            }
            if ($locked->supplier->archived_at !== null) {
                throw new ConflictHttpException('The supplier was archived before issue.');
            }
            abort_if($locked->lines->isEmpty(), 409, 'A purchase order needs at least one line before issue.');
            $locked->update(['state' => PurchaseOrderState::Issued, 'version' => $locked->version + 1, 'issue_key' => $key, 'issue_hash' => $hash, 'issued_at' => now()]);
            $this->recorder->record($locked, $actor, 'issued', PurchaseOrderState::Draft->value);

            return ['order' => $locked->load('history'), 'replayed' => false];
        });
    }
}
