<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'settlement_batch_id', 'sale_tender_id', 'external_reference', 'expected_amount', 'settled_amount', 'fee_amount', 'difference_amount', 'state', 'exception_note', 'created_at'])] class SettlementLine extends Model
{
    use HasBinaryPublicId;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'settled_amount' => 'decimal:2', 'fee_amount' => 'decimal:2', 'difference_amount' => 'decimal:2'];
    }
}
