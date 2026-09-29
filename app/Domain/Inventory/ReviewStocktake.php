<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\StocktakeState;
use App\Models\SecurityAuditEvent;
use App\Models\Stocktake;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReviewStocktake
{
    public function handle(Stocktake $stocktake, User $actor, int $expectedVersion): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $actor, $expectedVersion): Stocktake {
            $locked = Stocktake::query()->with('lines')->lockForUpdate()->findOrFail($stocktake->id);
            if ($locked->state !== StocktakeState::Counting || $locked->version !== $expectedVersion) {
                throw new ConflictHttpException('The stocktake state or version changed before review.');
            }
            if ($locked->lines->contains(fn ($line): bool => $line->counted_quantity_base === null)) {
                throw new ConflictHttpException('Every stocktake line must be counted before review.');
            }
            $locked->update(['state' => StocktakeState::Review, 'version' => $locked->version + 1, 'reviewed_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'inventory.stocktake.reviewed', 'outcome' => 'succeeded', 'metadata' => ['stocktake_id' => $locked->public_id, 'line_count' => $locked->lines->count()], 'occurred_at' => now()]);

            return $locked->refresh()->load(['location', 'lines.variant.product', 'lines.batch']);
        });
    }
}
