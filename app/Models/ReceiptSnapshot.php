<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $sale_id
 * @property int $version
 * @property string $content_hash
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $created_at
 * @property-read Sale $sale
 */
#[Fillable(['merchant_id', 'store_id', 'sale_id', 'version', 'content_hash', 'payload', 'created_at'])]
class ReceiptSnapshot extends Model
{
    use HasBinaryPublicId;

    public $timestamps = false;

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'payload' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
