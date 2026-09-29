<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryDisposition;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\Enums\StockTransferState;
use App\Domain\Inventory\Enums\TransferReceiptResolution;
use App\Domain\Shared\Math\Decimal;
use App\Models\ProductBatch;
use App\Models\SecurityAuditEvent;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StockTransferReceipt;
use App\Models\StockTransferReceiptLine;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReceiveStockTransfer
{
    public function __construct(private readonly PostStockMovement $post, private readonly BatchExpiryPolicy $expiryPolicy, private readonly PostInventoryValuation $valuation) {}

    /**
     * @param  list<array{transfer_line_id: string, quantity_destination_base: string}>  $lines
     * @return array{transfer: StockTransfer, receipt: StockTransferReceipt, replayed: bool}
     */
    public function handle(StockTransfer $transfer, User $actor, string $key, int $expectedVersion, TransferReceiptResolution $resolution, ?string $reason, array $lines): array
    {
        $normalized = array_map(fn (array $line): array => ['transfer_line_id' => $line['transfer_line_id'], 'quantity' => Decimal::fixed(BigDecimal::of($line['quantity_destination_base']), 6)], $lines);
        usort($normalized, fn (array $a, array $b): int => $a['transfer_line_id'] <=> $b['transfer_line_id']);
        $hash = hash('sha256', json_encode(['expected_version' => $expectedVersion, 'resolution' => $resolution->value, 'reason' => $reason, 'lines' => $normalized], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($transfer, $actor, $key, $expectedVersion, $resolution, $reason, $normalized, $hash): array {
            $locked = StockTransfer::query()->with(['destinationStore', 'destinationLocation', 'lines.sourceBatch', 'lines.destinationVariant.product'])->lockForUpdate()->findOrFail($transfer->id);
            $existing = StockTransferReceipt::query()->where('merchant_id', $locked->merchant_id)->where('stock_transfer_id', $locked->id)->where('idempotency_key', $key)->with('lines')->first();
            if ($existing instanceof StockTransferReceipt) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The transfer receipt key was reused with different data.');
                }

                return ['transfer' => $locked, 'receipt' => $existing, 'replayed' => true];
            }
            if (! in_array($locked->state, [StockTransferState::Dispatched, StockTransferState::PartiallyReceived], true) || $locked->version !== $expectedVersion) {
                throw new ConflictHttpException('The transfer state or version changed before receipt.');
            }
            $lineByPublicId = $locked->lines->keyBy('public_id');
            $outstanding = $locked->lines->filter(fn (StockTransferLine $line): bool => BigDecimal::of($line->received_destination_quantity_base)->isLessThan(BigDecimal::of($line->expected_destination_quantity_base)))->pluck('public_id')->sort()->values()->all();
            if (in_array($resolution, [TransferReceiptResolution::ShortClose, TransferReceiptResolution::ExcessAccept], true)) {
                $provided = collect($normalized)->pluck('transfer_line_id')->sort()->values()->all();
                if ($provided !== $outstanding) {
                    throw new ConflictHttpException('A closing discrepancy resolution must state a received quantity for every outstanding line.');
                }
            }
            $receipt = StockTransferReceipt::query()->create([
                'merchant_id' => $locked->merchant_id, 'stock_transfer_id' => $locked->id, 'received_by_user_id' => $actor->id,
                'idempotency_key' => $key, 'payload_hash' => $hash, 'resolution' => $resolution, 'reason' => $reason, 'received_at' => now(),
            ]);
            foreach ($normalized as $input) {
                $line = $lineByPublicId->get($input['transfer_line_id']);
                abort_unless($line instanceof StockTransferLine, 422, 'A transfer receipt line is invalid.');
                $quantity = BigDecimal::of($input['quantity']);
                if ($quantity->isZero() && $resolution !== TransferReceiptResolution::ShortClose) {
                    throw new ConflictHttpException('Only a short-close may record zero received quantity for a missing outstanding line.');
                }
                $expected = BigDecimal::of($line->expected_destination_quantity_base);
                $previous = BigDecimal::of($line->received_destination_quantity_base);
                $newTotal = $previous->plus($quantity);
                if ($resolution !== TransferReceiptResolution::ExcessAccept && $newTotal->isGreaterThan($expected)) {
                    throw new ConflictHttpException('Received quantity exceeds the dispatched conversion; use an authorized excess resolution.');
                }
                $destinationBatch = null;
                $destinationCostEntryId = null;
                if (! $quantity->isZero()) {
                    $destinationBatch = $this->destinationBatch($locked, $line);
                    if ($line->destination_unit_cost !== null) {
                        $costResult = $this->valuation->inbound(
                            $locked->destinationStore, $line->destinationVariant, InventoryCostEntryKind::TransferIn, $input['quantity'], $line->destination_unit_cost,
                            'stock_transfer_receipt', $receipt->public_id, $line->public_id, now()->toIso8601String(),
                            fn (string $unitCost, InventoryCostState $costState) => $this->post->handle(new StockMovementCommand(
                                location: $locked->destinationLocation, variant: $line->destinationVariant, batch: $destinationBatch,
                                kind: InventoryMovementKind::TransferIn, quantityBase: $input['quantity'], sourceType: 'stock_transfer_receipt',
                                sourceReference: $receipt->public_id, sourceLineReference: $line->public_id, actor: $actor,
                                reasonText: 'Receive transfer '.$locked->number, unitCost: $unitCost, costState: $costState,
                            ))['movement'],
                        );
                        $destinationCostEntryId = $costResult['entry']->id;
                    } else {
                        $this->post->handle(new StockMovementCommand(
                            location: $locked->destinationLocation, variant: $line->destinationVariant, batch: $destinationBatch,
                            kind: InventoryMovementKind::TransferIn, quantityBase: $input['quantity'], sourceType: 'stock_transfer_receipt',
                            sourceReference: $receipt->public_id, sourceLineReference: $line->public_id, actor: $actor,
                            reasonText: 'Receive transfer '.$locked->number,
                        ));
                    }
                }
                StockTransferReceiptLine::query()->create([
                    'merchant_id' => $locked->merchant_id, 'stock_transfer_receipt_id' => $receipt->id, 'stock_transfer_line_id' => $line->id,
                    'destination_product_batch_id' => $destinationBatch?->id, 'destination_cost_entry_id' => $destinationCostEntryId, 'received_destination_quantity_base' => $input['quantity'],
                ]);
                $line->update([
                    'received_destination_quantity_base' => Decimal::fixed($newTotal, 6),
                    'excess_destination_quantity_base' => Decimal::fixed($newTotal->isGreaterThan($expected) ? $newTotal->minus($expected) : BigDecimal::zero(), 6),
                ]);
            }
            $locked->load('lines');
            $allExact = $locked->lines->every(fn (StockTransferLine $line): bool => BigDecimal::of($line->received_destination_quantity_base)->isEqualTo(BigDecimal::of($line->expected_destination_quantity_base)));
            $allCovered = $locked->lines->every(fn (StockTransferLine $line): bool => BigDecimal::of($line->received_destination_quantity_base)->isGreaterThanOrEqualTo(BigDecimal::of($line->expected_destination_quantity_base)));
            $hasExcess = $locked->lines->contains(fn (StockTransferLine $line): bool => BigDecimal::of($line->received_destination_quantity_base)->isGreaterThan(BigDecimal::of($line->expected_destination_quantity_base)));
            if ($resolution === TransferReceiptResolution::Complete && ! $allExact) {
                throw new ConflictHttpException('Complete receipt requires every line to equal its expected destination quantity.');
            }
            if ($resolution === TransferReceiptResolution::ExcessAccept && (! $allCovered || ! $hasExcess)) {
                throw new ConflictHttpException('Excess acceptance must cover every outstanding line and record at least one verified excess.');
            }
            $close = $allExact || in_array($resolution, [TransferReceiptResolution::ShortClose, TransferReceiptResolution::ExcessAccept], true);
            if ($close && $resolution === TransferReceiptResolution::ShortClose) {
                foreach ($locked->lines as $line) {
                    $short = BigDecimal::of($line->expected_destination_quantity_base)->minus($line->received_destination_quantity_base);
                    $line->update(['short_destination_quantity_base' => Decimal::fixed($short->isPositive() ? $short : BigDecimal::zero(), 6)]);
                }
            }
            $locked->update(['state' => $close ? StockTransferState::Received : StockTransferState::PartiallyReceived, 'version' => $locked->version + 1, 'received_at' => $close ? now() : null]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->destination_store_id, 'actor_user_id' => $actor->id, 'event_code' => 'inventory.transfer.received', 'outcome' => 'succeeded', 'metadata' => ['transfer_id' => $locked->public_id, 'receipt_id' => $receipt->public_id, 'resolution' => $resolution->value, 'closed' => $close], 'occurred_at' => now()]);

            return ['transfer' => $locked->refresh()->load(['sourceStore', 'destinationStore', 'sourceLocation', 'destinationLocation', 'lines.sourceVariant.product', 'lines.destinationVariant.product', 'lines.sourceBatch']), 'receipt' => $receipt->load('lines.destinationBatch'), 'replayed' => false];
        });
    }

    private function destinationBatch(StockTransfer $transfer, StockTransferLine $line): ?ProductBatch
    {
        $sourceBatch = $line->sourceBatch;
        if ($sourceBatch === null) {
            return null;
        }
        if ($this->expiryPolicy->isExpired($sourceBatch, $transfer->destinationStore) && $transfer->destinationLocation->disposition === InventoryDisposition::Sellable) {
            throw new ConflictHttpException('An expired batch must be received into quarantine or unavailable stock.');
        }
        $batch = ProductBatch::query()->where('merchant_id', $transfer->merchant_id)->where('product_variant_id', $line->destination_product_variant_id)->where('code', $sourceBatch->code)->first();
        if ($batch instanceof ProductBatch && $batch->expiry_date->toDateString() !== $sourceBatch->expiry_date->toDateString()) {
            throw new ConflictHttpException('The destination batch code has conflicting expiry facts.');
        }

        return $batch ?? ProductBatch::query()->create([
            'merchant_id' => $transfer->merchant_id, 'product_variant_id' => $line->destination_product_variant_id,
            'origin_store_id' => $transfer->destination_store_id, 'code' => $sourceBatch->code,
            'expiry_date' => $sourceBatch->expiry_date, 'received_on' => now()->setTimezone($transfer->destinationStore->timezone)->toDateString(),
            'supplier_reference' => $sourceBatch->supplier_reference,
        ]);
    }
}
