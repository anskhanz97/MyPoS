<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\ExtraGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property int $minimum_selected
 * @property int $maximum_selected
 * @property-read Collection<int, ExtraOption> $options
 */
#[Fillable(['merchant_id', 'product_id', 'name', 'minimum_selected', 'maximum_selected', 'sort_order'])]
class ExtraGroup extends Model
{
    /** @use HasFactory<ExtraGroupFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return HasMany<ExtraOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ExtraOption::class);
    }
}
