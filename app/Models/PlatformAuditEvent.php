<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\PlatformAuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['platform_operator_id', 'merchant_id', 'event_code', 'outcome', 'aggregate_type', 'aggregate_public_id', 'reason', 'metadata', 'occurred_at'])]
class PlatformAuditEvent extends Model
{
    /** @use HasFactory<PlatformAuditEventFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['aggregate_public_id' => BinaryUuid::class, 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
