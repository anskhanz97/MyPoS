<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Purchasing\Enums\PurchaseReturnStatus;
use App\Domain\Purchasing\Enums\SupplierCreditStatus;
use App\Domain\Shared\Math\Decimal;
use App\Models\OutboxEvent;
use App\Models\PurchaseReturn;
use App\Models\SecurityAuditEvent;
use App\Models\SupplierCredit;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReversePurchaseReturn
{
    public function __construct(private readonly PostInventoryValuation $valuation, private readonly PostStockMovement $stock) {}

    /** @return array{return: PurchaseReturn, replayed: bool} */
    public function handle(PurchaseReturn $purchaseReturn, User $actor, string $reason): array
    {
        return DB::transaction(function () use ($purchaseReturn, $actor, $reason): array {
            $locked = PurchaseReturn::query()->with(['receipt.purchaseOrder', 'location.store', 'supplier', 'credit', 'lines.receiptLine.purchaseOrderLine.variant', 'lines.batch', 'lines.stockMovement', 'lines.costEntry'])->lockForUpdate()->findOrFail($purchaseReturn->id);
            if ($locked->status === PurchaseReturnStatus::Reversed) {
                if ($locked->reversal_reason !== $reason) {
                    throw new ConflictHttpException('This purchase return was already reversed with a different reason.');
                }

                return ['return' => $locked, 'replayed' => true];
            }
            $credit = $locked->credit;
            abort_unless($credit instanceof SupplierCredit, 500);
            foreach ($locked->lines->sortByDesc('inventory_cost_entry_id') as $line) {
                $source = $line->receiptLine;
                $this->valuation->reverseLatest(
                    $line->costEntry, $locked->public_id, now()->toIso8601String(),
                    fn (?string $unitCost, InventoryCostState $costState) => $this->stock->handle(new StockMovementCommand(
                        location: $locked->location, variant: $source->purchaseOrderLine->variant, batch: $line->batch, kind: InventoryMovementKind::Correction,
                        quantityBase: Decimal::fixed(BigDecimal::of($line->returned_quantity_base), 6), sourceType: 'purchase_return_reversal',
                        sourceReference: $locked->public_id, sourceLineReference: $line->public_id, actor: $actor, reverses: $line->stockMovement,
                        reasonText: $reason, unitCost: $unitCost, costState: $costState,
                    ))['movement'],
                    InventoryCostEntryKind::PurchaseReturnReversal,
                    'purchase_return_reversal',
                );
            }
            SupplierCredit::query()->create([
                'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'supplier_id' => $locked->supplier_id, 'purchase_return_id' => $locked->id,
                'reverses_credit_id' => $credit->id, 'entry_kind' => 'reversal', 'status' => SupplierCreditStatus::Reversed, 'currency' => 'PKR',
                'amount' => Decimal::fixed(BigDecimal::of($credit->amount)->negated(), 2), 'supplier_reference' => $credit->supplier_reference, 'occurred_at' => now(),
            ]);
            $credit->update(['status' => SupplierCreditStatus::Reversed]);
            $locked->update(['status' => PurchaseReturnStatus::Reversed, 'credit_status' => SupplierCreditStatus::Reversed, 'reversed_at' => now(), 'reversed_by_user_id' => $actor->id, 'reversal_reason' => $reason]);
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'purchasing.return.reversed', 'aggregate_type' => 'purchase_return', 'aggregate_id' => $locked->public_id, 'payload' => ['purchase_return_id' => $locked->public_id], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'purchasing.return.reversed', 'outcome' => 'succeeded', 'metadata' => ['purchase_return_id' => $locked->public_id, 'reason' => $reason], 'occurred_at' => now()]);

            return ['return' => $locked->refresh()->load(['receipt.purchaseOrder', 'location', 'supplier', 'credit', 'lines.receiptLine', 'lines.batch', 'lines.stockMovement', 'lines.costEntry']), 'replayed' => false];
        }, attempts: 3);
    }
}
