<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'user_id', 'resource', 'name', 'mapping'])]
class ImportMappingPreset extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }
}
