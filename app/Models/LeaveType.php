<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $id @property string $public_id @property int $merchant_id @property string $name @property bool $is_paid @property bool $is_active */
#[Fillable(['merchant_id', 'code', 'name', 'is_paid', 'is_active'])]
class LeaveType extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'is_active' => 'boolean'];
    }
}
