<?php

namespace App\Domain\Purchasing;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Domain\Shared\Math\Decimal;
use App\Models\PurchaseOrder;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CancelPurchaseOrder
{
    public function __construct(private readonly PurchaseOrderRecorder $recorder) {}

    public function handle(PurchaseOrder $order, User $actor, int $expectedVersion, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor, $expectedVersion, $reason): PurchaseOrder {
            $locked = PurchaseOrder::query()->with(['supplier', 'store', 'lines.variant.product', 'lines.purchaseUnit'])->lockForUpdate()->findOrFail($order->id);
            if ($locked->version !== $expectedVersion || in_array($locked->state, [PurchaseOrderState::Received, PurchaseOrderState::Cancelled], true)) {
                throw new ConflictHttpException('The purchase order state or version no longer permits cancellation.');
            }
            $fromState = $locked->state->value;
            foreach ($locked->lines as $line) {
                $outstanding = BigDecimal::of($line->ordered_quantity_base)->minus($line->received_quantity_base);
                $line->update(['cancelled_quantity_base' => Decimal::fixed($outstanding->isPositive() ? $outstanding : BigDecimal::zero(), 6)]);
            }
            $locked->update(['state' => PurchaseOrderState::Cancelled, 'version' => $locked->version + 1, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->recorder->record($locked, $actor, 'cancelled', $fromState, $reason);

            return $locked->load('history');
        });
    }
}
