<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntegrationCallback;
use App\Models\MerchantIntegration;
use App\Models\NotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IntegrationCallbackController extends Controller
{
    public function twilio(Request $request, MerchantIntegration $merchantIntegration): JsonResponse
    {
        abort_unless($merchantIntegration->provider === 'twilio_sms', 404);
        $authToken = (string) data_get($merchantIntegration->credentials, 'auth_token');
        $expected = base64_encode(hash_hmac('sha1', $request->fullUrl().$this->twilioParameterString($request->post()), $authToken, true));
        abort_unless($authToken !== '' && hash_equals($expected, (string) $request->header('X-Twilio-Signature')), 403, 'Invalid callback signature.');
        $messageId = $request->string('MessageSid')->toString();
        abort_if($messageId === '', 422, 'MessageSid is required.');
        $status = $request->string('MessageStatus')->toString();
        $delivery = NotificationDelivery::query()->where('merchant_integration_id', $merchantIntegration->id)->where('provider_message_id', $messageId)->first();
        $this->record($merchantIntegration, $delivery, $messageId.':'.$status, 'message.'.$status, $status, $request->getContent(), now());
        $this->applyDeliveryState($delivery, $status, $request->input('NumSegments'), $request->input('Price'), $request->input('PriceUnit'));

        return response()->json(['received' => true]);
    }

    public function metaVerify(Request $request, MerchantIntegration $merchantIntegration): mixed
    {
        abort_unless($merchantIntegration->provider === 'meta_whatsapp', 404);
        $valid = $request->query('hub_mode') === 'subscribe' && hash_equals((string) data_get($merchantIntegration->credentials, 'verify_token'), (string) $request->query('hub_verify_token'));
        abort_unless($valid, 403, 'Webhook verification failed.');

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function meta(Request $request, MerchantIntegration $merchantIntegration): JsonResponse
    {
        abort_unless($merchantIntegration->provider === 'meta_whatsapp', 404);
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) data_get($merchantIntegration->credentials, 'app_secret'));
        abort_unless(hash_equals($expected, $signature), 403, 'Invalid callback signature.');
        foreach ((array) data_get($request->json()->all(), 'entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                foreach ((array) data_get($change, 'value.statuses', []) as $status) {
                    $messageId = (string) ($status['id'] ?? '');
                    $outcome = (string) ($status['status'] ?? 'unknown');
                    if ($messageId === '') {
                        continue;
                    }
                    $delivery = NotificationDelivery::query()->where('merchant_integration_id', $merchantIntegration->id)->where('provider_message_id', $messageId)->first();
                    $occurredAt = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp']) : now();
                    $this->record($merchantIntegration, $delivery, $messageId.':'.$outcome, 'message.'.$outcome, $outcome, json_encode($status, JSON_THROW_ON_ERROR), $occurredAt);
                    $this->applyDeliveryState($delivery, $outcome);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    /** @param array<string,mixed> $parameters */
    private function twilioParameterString(array $parameters): string
    {
        ksort($parameters, SORT_STRING);

        return collect($parameters)->map(fn (mixed $value, string $key): string => $key.(is_array($value) ? implode('', $value) : (string) $value))->implode('');
    }

    private function record(MerchantIntegration $integration, ?NotificationDelivery $delivery, string $eventId, string $eventType, string $outcome, string $payload, mixed $occurredAt): void
    {
        IntegrationCallback::query()->firstOrCreate(['merchant_integration_id' => $integration->id, 'provider_event_id' => $eventId], ['notification_delivery_id' => $delivery?->id, 'event_type' => $eventType, 'outcome' => $outcome, 'payload_hash' => hash('sha256', $payload), 'occurred_at' => $occurredAt]);
    }

    private function applyDeliveryState(?NotificationDelivery $delivery, string $status, mixed $segments = null, mixed $price = null, mixed $currency = null): void
    {
        if (! $delivery instanceof NotificationDelivery) {
            return;
        }
        $state = match ($status) {
            'delivered' => 'delivered', 'read' => 'read', 'failed', 'undelivered' => 'failed', default => 'accepted',
        };
        $attributes = ['state' => $state];
        $attributes[$state === 'read' ? 'read_at' : ($state === 'delivered' ? 'delivered_at' : ($state === 'failed' ? 'failed_at' : 'sent_at'))] = now();
        if (is_numeric($segments)) {
            $attributes['segment_count'] = (int) $segments;
        }
        if (is_numeric($price)) {
            $attributes['provider_cost_minor'] = (int) round(abs((float) $price) * 100);
            $attributes['provider_cost_currency'] = strtoupper((string) $currency);
        }
        $delivery->update($attributes);
    }
}
