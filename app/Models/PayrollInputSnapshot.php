<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property string $content_hash
 * @property array<string, mixed> $payload
 */
#[Fillable(['merchant_id', 'staff_profile_id', 'frozen_by_user_id', 'period_start', 'period_end', 'content_hash', 'payload', 'frozen_at'])]
class PayrollInputSnapshot extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'payload' => 'array', 'frozen_at' => 'immutable_datetime'];
    }
}
