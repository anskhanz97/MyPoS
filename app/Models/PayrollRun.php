<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property string $state
 * @property int $version
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 */
#[Fillable(['merchant_id', 'period_start', 'period_end', 'state', 'version', 'created_by_user_id', 'posted_by_user_id', 'posted_at'])]
class PayrollRun extends Model
{
    use HasBinaryPublicId;

    /** @return HasMany<PayrollItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'posted_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
