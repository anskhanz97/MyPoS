<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\EntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property string $code @property bool $enabled @property int|null $limit_value */
#[Fillable(['plan_id', 'code', 'enabled', 'limit_value'])]
class Entitlement extends Model
{
    /** @use HasFactory<EntitlementFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'limit_value' => 'integer'];
    }
}
