<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\SupportAccessGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property string $scope
 * @property string $purpose
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property-read Merchant $merchant
 * @property-read PlatformOperator $operator
 */
#[Fillable(['merchant_id', 'platform_operator_id', 'granted_by_user_id', 'scope', 'purpose', 'expires_at', 'revoked_at'])]
class SupportAccessGrant extends Model
{
    /** @use HasFactory<SupportAccessGrantFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<PlatformOperator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(PlatformOperator::class, 'platform_operator_id');
    }

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
