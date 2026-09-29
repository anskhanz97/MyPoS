<?php

namespace App\Domain\Inventory;

use App\Models\BatchSelectionSnapshot;
use App\Models\Store;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ValidateBatchSelectionSnapshot
{
    public function __construct(private readonly BatchExpiryPolicy $expiryPolicy) {}

    public function handle(BatchSelectionSnapshot $snapshot, Store $store): void
    {
        if ($snapshot->merchant_id !== $store->merchant_id || $snapshot->store_id !== $store->id || $snapshot->expires_at->isPast()) {
            throw new ConflictHttpException('The batch selection snapshot is missing, expired or outside this store.');
        }
        $snapshot->loadMissing('lines.batch');
        foreach ($snapshot->lines as $line) {
            if ($line->batch->archived_at !== null
                || $line->batch->expiry_date->toDateString() !== $line->expiry_date_snapshot->toDateString()
                || $this->expiryPolicy->isExpired($line->batch, $store)) {
                throw new ConflictHttpException('Batch facts changed or expired after selection; select batches again.');
            }
        }
    }
}
