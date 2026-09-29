<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\PlatformOperatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'is_active'])]
class PlatformOperator extends Model
{
    /** @use HasFactory<PlatformOperatorFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
