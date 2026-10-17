<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $public_id @property int $merchant_id @property int|null $store_id @property string $channel @property string $template_key @property string $source_type @property string $source_reference @property string $recipient @property string $state @property int $attempt_count */
#[Fillable(['merchant_id', 'store_id', 'requested_by_user_id', 'merchant_integration_id', 'channel', 'message_class', 'template_key', 'source_type', 'source_reference', 'recipient', 'deduplication_key', 'state', 'attempt_count', 'provider_message_id', 'failure_code', 'safe_failure_message', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'segment_count', 'provider_cost_minor', 'provider_cost_currency'])]
class NotificationDelivery extends Model
{
    use HasBinaryPublicId;

    /** @return array<string, mixed> */
    public function logContext(): array
    {
        return ['merchant_id' => $this->merchant_id, 'store_id' => $this->store_id, 'actor_user_id' => $this->requested_by_user_id, 'provider' => match ($this->channel) {
            'sms' => 'twilio_sms', 'whatsapp' => 'meta_whatsapp', default => 'smtp'
        }, 'operation_id' => 'notification:'.$this->public_id, 'subject' => $this->source_type.':'.$this->source_reference];
    }

    /** @return HasMany<NotificationAttempt,$this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationAttempt::class);
    }

    protected function casts(): array
    {
        return ['attempt_count' => 'integer', 'sent_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime', 'read_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime', 'segment_count' => 'integer', 'provider_cost_minor' => 'integer'];
    }
}
