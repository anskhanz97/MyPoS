<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $purchase_order_id
 * @property string $action
 * @property string|null $from_state
 * @property string $to_state
 * @property int $version
 * @property string|null $reason
 * @property array<string, mixed> $snapshot
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['merchant_id', 'store_id', 'purchase_order_id', 'actor_user_id', 'action', 'from_state', 'to_state', 'version', 'reason', 'snapshot', 'occurred_at'])]
class PurchaseOrderHistory extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'snapshot' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
