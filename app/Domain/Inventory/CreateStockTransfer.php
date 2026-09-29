<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\StockTransferState;
use App\Domain\Shared\Math\Decimal;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\SecurityAuditEvent;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateStockTransfer
{
    public function __construct(private readonly InventoryScope $scope, private readonly TransferVariantResolver $resolver) {}

    /** @param list<array{source_variant_id: string, source_batch_id?: string|null, quantity_base: string}> $lines */
    public function handle(Merchant $merchant, Store $source, Store $destination, InventoryLocation $sourceLocation, InventoryLocation $destinationLocation, User $actor, ?string $notes, array $lines): StockTransfer
    {
        $this->scope->assertLocation($merchant, $source, $sourceLocation);
        $this->scope->assertLocation($merchant, $destination, $destinationLocation);

        return DB::transaction(function () use ($merchant, $source, $destination, $sourceLocation, $destinationLocation, $actor, $notes, $lines): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'merchant_id' => $merchant->id, 'source_store_id' => $source->id, 'destination_store_id' => $destination->id,
                'source_inventory_location_id' => $sourceLocation->id, 'destination_inventory_location_id' => $destinationLocation->id,
                'created_by_user_id' => $actor->id, 'number' => 'TR-'.mb_strtoupper(substr((string) Str::ulid(), -12)), 'state' => StockTransferState::Draft,
                'notes' => $notes,
            ]);
            foreach ($lines as $input) {
                $sourceVariant = (new ProductVariant)->resolveRouteBinding($input['source_variant_id']);
                abort_unless($sourceVariant instanceof ProductVariant, 422, 'A source variant is invalid.');
                $this->scope->assertVariant($merchant, $source, $sourceVariant);
                $resolved = $this->resolver->resolve($merchant, $source, $destination, $sourceVariant);
                abort_unless($sourceVariant->product->batch_tracking_enabled === $resolved['destination']->product->batch_tracking_enabled, 409, 'Mapped variants must use compatible batch tracking.');
                $batch = isset($input['source_batch_id']) ? (new ProductBatch)->resolveRouteBinding((string) $input['source_batch_id']) : null;
                abort_unless(! isset($input['source_batch_id']) || $batch instanceof ProductBatch, 422, 'The source batch is invalid.');
                if ($sourceVariant->product->batch_tracking_enabled) {
                    abort_unless($batch instanceof ProductBatch && $batch->product_variant_id === $sourceVariant->id, 409, 'A compatible source batch is required.');
                } else {
                    abort_unless($batch === null, 409, 'An untracked product cannot use a batch.');
                }
                $quantity = Decimal::fixed(BigDecimal::of($input['quantity_base']), 6);
                StockTransferLine::query()->create([
                    'merchant_id' => $merchant->id, 'stock_transfer_id' => $transfer->id, 'inventory_variant_mapping_id' => $resolved['mapping']?->id,
                    'source_product_variant_id' => $sourceVariant->id, 'destination_product_variant_id' => $resolved['destination']->id,
                    'source_product_batch_id' => $batch?->id, 'factor_to_destination_base' => $resolved['factor'],
                    'requested_source_quantity_base' => $quantity,
                ]);
            }
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $source->id, 'actor_user_id' => $actor->id, 'event_code' => 'inventory.transfer.created', 'outcome' => 'succeeded', 'metadata' => ['transfer_id' => $transfer->public_id, 'destination_store_id' => $destination->public_id, 'line_count' => count($lines)], 'occurred_at' => now()]);

            return $transfer->load(['sourceStore', 'destinationStore', 'sourceLocation', 'destinationLocation', 'lines.sourceVariant.product', 'lines.destinationVariant.product', 'lines.sourceBatch']);
        });
    }
}
