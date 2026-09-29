<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $public_id
 * @property int $customer_display_pairing_id
 * @property int|null $cart_id
 * @property int|null $sale_id
 * @property int $sequence
 * @property string $state
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $clear_at
 * @property CarbonImmutable $published_at
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'customer_display_pairing_id', 'cart_id', 'sale_id', 'sequence', 'state', 'payload', 'clear_at', 'published_at'])]
class CustomerDisplaySnapshot extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'payload' => 'array', 'clear_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }
}
