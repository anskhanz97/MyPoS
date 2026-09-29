<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $public_id @property int $merchant_id @property int $store_id @property int $fiscal_configuration_id @property string $local_document_type @property string $local_document_reference @property string $local_document_number @property string $state @property array<string,mixed> $payload_snapshot @property string $payload_hash @property string|null $authority_reference @property int $attempt_count */
#[Fillable(['merchant_id', 'store_id', 'fiscal_configuration_id', 'local_document_type', 'local_document_reference', 'local_document_number', 'state', 'payload_snapshot', 'payload_hash', 'authority_reference', 'authority_dated_at', 'qr_value', 'failure_category', 'failure_code', 'safe_failure_message', 'unknown_since', 'accepted_at'])]
class FiscalDocument extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<FiscalConfiguration,$this> */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(FiscalConfiguration::class, 'fiscal_configuration_id');
    }

    /** @return HasMany<FiscalSubmissionAttempt,$this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(FiscalSubmissionAttempt::class);
    }

    /** @return array<string,mixed> */
    public function payloadData(): array
    {
        $value = $this->getAttribute('payload_snapshot');
        if (! is_array($value)) {
            return [];
        } $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    protected function casts(): array
    {
        return ['payload_snapshot' => 'array', 'authority_dated_at' => 'immutable_datetime', 'unknown_since' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
