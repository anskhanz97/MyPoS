<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'expense_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'created_at'])] class ExpenseAttachment extends Model
{
    use HasBinaryPublicId;

    public $timestamps = false;
}
