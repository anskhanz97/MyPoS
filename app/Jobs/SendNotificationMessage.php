<?php

namespace App\Jobs;

use App\Domain\Integrations\MessageTransport;
use App\Models\MerchantIntegration;
use App\Models\NotificationAttempt;
use App\Models\NotificationConsent;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendNotificationMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $deliveryId) {}

    /**
     * Execute the job.
     */
    public function handle(MessageTransport $transport): void
    {
        $delivery = DB::transaction(function (): ?NotificationDelivery {
            $locked = NotificationDelivery::query()->lockForUpdate()->findOrFail($this->deliveryId);
            if (in_array($locked->state, ['accepted', 'delivered', 'read', 'sending'], true)) {
                return null;
            }
            $integration = MerchantIntegration::query()->find($locked->merchant_integration_id);
            if (! $integration instanceof MerchantIntegration || $integration->state !== 'active') {
                $locked->update(['state' => 'cancelled', 'failure_code' => 'integration_disabled', 'safe_failure_message' => 'Delivery stopped because the channel is disabled.', 'failed_at' => now()]);

                return null;
            }
            $consented = NotificationConsent::query()->where('merchant_id', $locked->merchant_id)->where('normalized_phone', $locked->recipient)->where('channel', $locked->channel)->where('purpose', $locked->message_class)->where('state', 'granted')->exists();
            if (! $consented) {
                $locked->update(['state' => 'cancelled', 'failure_code' => 'consent_missing', 'safe_failure_message' => 'Delivery stopped because recipient consent is unavailable.', 'failed_at' => now()]);

                return null;
            }
            $limit = max(1, (int) ($integration->config['daily_limit'] ?? 250));
            $used = NotificationDelivery::query()->where('merchant_integration_id', $integration->id)->whereDate('created_at', today())->whereIn('state', ['sending', 'accepted', 'delivered', 'read'])->count();
            if ($used >= $limit) {
                $locked->update(['state' => 'failed', 'failure_code' => 'daily_limit', 'safe_failure_message' => 'The channel daily sending limit has been reached.', 'failed_at' => now()]);

                return null;
            }
            $locked->update(['state' => 'sending', 'attempt_count' => $locked->attempt_count + 1, 'failure_code' => null, 'safe_failure_message' => null]);

            return $locked->refresh();
        });
        if (! $delivery instanceof NotificationDelivery) {
            return;
        }
        try {
            $integration = MerchantIntegration::query()->findOrFail($delivery->merchant_integration_id);
            $result = $transport->send($integration, $delivery, 'Your MyPoS receipt is ready. Reference: '.$delivery->source_reference);
            NotificationAttempt::query()->create(['notification_delivery_id' => $delivery->id, 'attempt_number' => $delivery->attempt_count, 'outcome' => 'accepted', 'provider_message_id' => $result['id'], 'attempted_at' => now()]);
            $delivery->update(['state' => 'accepted', 'provider_message_id' => $result['id'], 'sent_at' => now(), 'segment_count' => $result['segments']]);
        } catch (Throwable $exception) {
            NotificationAttempt::query()->create(['notification_delivery_id' => $delivery->id, 'attempt_number' => $delivery->attempt_count, 'outcome' => 'failed', 'failure_code' => 'provider_error', 'safe_message' => 'The provider did not accept the message.', 'attempted_at' => now()]);
            $delivery->update(['state' => 'failed', 'failure_code' => 'provider_error', 'safe_failure_message' => 'The provider did not accept the message.', 'failed_at' => now()]);
            report($exception);
        }
    }
}
