<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $quote_version
 * @property int $cart_version
 * @property string $pricing_version
 * @property array<string, mixed> $payload
 * @property string $total
 * @property CarbonImmutable $quoted_at
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['merchant_id', 'store_id', 'cart_id', 'quoted_by_user_id', 'quote_version', 'cart_version', 'pricing_version', 'content_hash', 'payload', 'total', 'quoted_at', 'expires_at'])]
class CartQuote extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    protected function casts(): array
    {
        return ['quote_version' => 'integer', 'cart_version' => 'integer', 'payload' => 'array', 'total' => 'decimal:2', 'quoted_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
