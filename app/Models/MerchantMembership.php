<?php

namespace App\Models;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\MerchantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MembershipRole $role
 * @property MembershipStatus $status
 * @property string|null $pin_hash
 * @property CarbonImmutable|null $pin_changed_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['merchant_id', 'user_id', 'role', 'status', 'pin_hash', 'pin_changed_at', 'revoked_at'])]
#[Hidden(['pin_hash'])]
class MerchantMembership extends Model
{
    /** @use HasFactory<MerchantMembershipFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StoreAssignment, $this> */
    public function storeAssignments(): HasMany
    {
        return $this->hasMany(StoreAssignment::class);
    }

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'status' => MembershipStatus::class,
            'pin_hash' => 'hashed',
            'pin_changed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
