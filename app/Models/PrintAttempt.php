<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $print_job_id
 * @property string $helper_event_id
 * @property string $payload_hash
 * @property int $attempt_number
 * @property string $outcome
 * @property string $helper_id
 * @property string $helper_version
 * @property string $printer_name
 * @property string|null $error_code
 * @property string|null $safe_message
 * @property CarbonImmutable $reported_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['print_job_id', 'helper_event_id', 'payload_hash', 'attempt_number', 'outcome', 'helper_id', 'helper_version', 'printer_name', 'error_code', 'safe_message', 'reported_at', 'created_at'])]
class PrintAttempt extends Model
{
    use HasBinaryPublicId;

    public $timestamps = false;

    /** @return BelongsTo<PrintJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }

    protected function casts(): array
    {
        return ['attempt_number' => 'integer', 'reported_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
