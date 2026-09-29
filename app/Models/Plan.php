<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $public_id @property string $code @property string $name @property int $billing_period_days @property int $grace_days @property string $price @property string $currency @property bool $is_active @property-read Collection<int, Entitlement> $entitlements */
#[Fillable(['code', 'name', 'billing_period_days', 'grace_days', 'price', 'currency', 'is_active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return HasMany<Entitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    protected function casts(): array
    {
        return ['billing_period_days' => 'integer', 'grace_days' => 'integer', 'price' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
