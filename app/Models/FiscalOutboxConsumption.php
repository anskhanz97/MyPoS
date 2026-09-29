<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['outbox_event_id', 'fiscal_document_id', 'outcome', 'consumed_at'])] class FiscalOutboxConsumption extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['consumed_at' => 'immutable_datetime'];
    }
}
