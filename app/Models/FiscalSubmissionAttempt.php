<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fiscal_document_id', 'attempt_number', 'outcome', 'request_hash', 'response_hash', 'http_status', 'authority_code', 'safe_message', 'attempted_at'])] class FiscalSubmissionAttempt extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['attempt_number' => 'integer', 'http_status' => 'integer', 'attempted_at' => 'immutable_datetime'];
    }
}
