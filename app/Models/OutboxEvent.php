<?php

namespace App\Models;

use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['event_id', 'merchant_id', 'store_id', 'event_type', 'aggregate_type', 'aggregate_id', 'payload', 'occurred_at', 'available_at', 'published_at', 'attempts'])]
class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use HasFactory;

    /** @return HasOne<FiscalOutboxConsumption,$this> */
    public function fiscalConsumption(): HasOne
    {
        return $this->hasOne(FiscalOutboxConsumption::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime', 'available_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
