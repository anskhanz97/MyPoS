<?php

namespace App\Models;

use App\Domain\Tenancy\Enums\CustomerScope;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\SettingsVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int|null $store_id
 * @property CustomerScope $scope
 * @property int $version
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $published_at
 * @property CarbonImmutable|null $effective_at
 */
#[Fillable(['merchant_id', 'store_id', 'scope', 'version', 'payload', 'created_by_user_id', 'published_at', 'effective_at'])]
class SettingsVersion extends Model
{
    /** @use HasFactory<SettingsVersionFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function casts(): array
    {
        return ['scope' => CustomerScope::class, 'payload' => 'array', 'published_at' => 'immutable_datetime', 'effective_at' => 'immutable_datetime'];
    }
}
