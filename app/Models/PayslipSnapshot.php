<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $payload */
#[Fillable(['merchant_id', 'payroll_item_id', 'payload', 'content_hash', 'created_at'])]
class PayslipSnapshot extends Model
{
    use HasBinaryPublicId;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
