<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\Enums\StocktakeState;
use App\Models\SecurityAuditEvent;
use App\Models\Stocktake;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostStocktake
{
    public function __construct(private readonly PostStockMovement $post) {}

    /** @return array{stocktake: Stocktake, replayed: bool, movements_posted: int} */
    public function handle(Stocktake $stocktake, User $actor, string $key, int $expectedVersion, string $reason): array
    {
        return DB::transaction(function () use ($stocktake, $actor, $key, $expectedVersion, $reason): array {
            $locked = Stocktake::query()->with(['location', 'lines.variant.product', 'lines.batch'])->lockForUpdate()->findOrFail($stocktake->id);
            $hash = hash('sha256', json_encode(['expected_version' => $expectedVersion, 'reason' => $reason, 'lines' => $locked->lines->map(fn ($line): array => ['line' => $line->public_id, 'discrepancy' => $line->discrepancy_quantity_base])->all()], JSON_THROW_ON_ERROR));
            if ($locked->post_key !== null) {
                if ($locked->post_key === $key && hash_equals((string) $locked->post_hash, $hash)) {
                    return ['stocktake' => $locked, 'replayed' => true, 'movements_posted' => $locked->lines->whereNotNull('posted_stock_movement_id')->count()];
                }
                throw new ConflictHttpException('The stocktake was already posted with different command evidence.');
            }
            if ($locked->state !== StocktakeState::Review || $locked->version !== $expectedVersion) {
                throw new ConflictHttpException('The stocktake state or version changed before posting.');
            }
            $posted = 0;
            foreach ($locked->lines as $line) {
                if ($line->discrepancy_quantity_base === null || BigDecimal::of($line->discrepancy_quantity_base)->isZero()) {
                    continue;
                }
                $result = $this->post->handle(new StockMovementCommand(
                    location: $locked->location, variant: $line->variant, batch: $line->batch, kind: InventoryMovementKind::Stocktake,
                    quantityBase: $line->discrepancy_quantity_base, sourceType: 'stocktake', sourceReference: $locked->public_id,
                    sourceLineReference: $line->public_id, actor: $actor, reasonText: $reason,
                ));
                $line->update(['posted_stock_movement_id' => $result['movement']->id]);
                $posted++;
            }
            $locked->update(['state' => StocktakeState::Posted, 'version' => $locked->version + 1, 'post_key' => $key, 'post_hash' => $hash, 'posted_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'inventory.stocktake.posted', 'outcome' => 'succeeded', 'metadata' => ['stocktake_id' => $locked->public_id, 'movements_posted' => $posted, 'baseline_movement_id' => $locked->baseline_movement_id], 'occurred_at' => now()]);

            return ['stocktake' => $locked->refresh()->load(['location', 'lines.variant.product', 'lines.batch']), 'replayed' => false, 'movements_posted' => $posted];
        });
    }
}
