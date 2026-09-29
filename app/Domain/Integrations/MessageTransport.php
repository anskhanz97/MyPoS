<?php

namespace App\Domain\Integrations;

use App\Models\MerchantIntegration;
use App\Models\NotificationDelivery;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class MessageTransport
{
    /** @return array{id:string,state:string,segments:int|null} */
    public function send(MerchantIntegration $integration, NotificationDelivery $delivery, string $body): array
    {
        if ($integration->mode === 'sandbox') {
            return ['id' => 'sandbox-'.Str::uuid(), 'state' => 'accepted', 'segments' => $integration->provider === 'twilio_sms' ? max(1, (int) ceil(mb_strlen($body) / 160)) : null];
        }

        return match ($integration->provider) {
            'twilio_sms' => $this->twilio($integration, $delivery, $body),
            'meta_whatsapp' => $this->whatsApp($integration, $delivery),
            default => throw new RuntimeException('The selected integration cannot send messages.'),
        };
    }

    /** @return array{id:string,state:string,segments:int|null} */
    private function twilio(MerchantIntegration $integration, NotificationDelivery $delivery, string $body): array
    {
        $credentials = (array) $integration->credentials;
        $config = (array) $integration->config;
        $form = ['To' => $delivery->recipient, 'Body' => $body, 'StatusCallback' => route('api.v1.integrations.twilio.callback', $integration)];
        if (($config['messaging_service_sid'] ?? '') !== '') {
            $form['MessagingServiceSid'] = $config['messaging_service_sid'];
        } else {
            $form['From'] = $config['from_number'];
        }
        $response = Http::asForm()->withBasicAuth($credentials['account_sid'], $credentials['auth_token'])->timeout(10)->post("https://api.twilio.com/2010-04-01/Accounts/{$credentials['account_sid']}/Messages.json", $form);

        return $this->accepted($response, 'sid', isset($response['num_segments']) ? (int) $response['num_segments'] : null);
    }

    /** @return array{id:string,state:string,segments:int|null} */
    private function whatsApp(MerchantIntegration $integration, NotificationDelivery $delivery): array
    {
        $credentials = (array) $integration->credentials;
        $config = (array) $integration->config;
        $response = Http::withToken($credentials['access_token'])->timeout(10)->post("https://graph.facebook.com/{$config['api_version']}/{$config['phone_number_id']}/messages", ['messaging_product' => 'whatsapp', 'to' => ltrim($delivery->recipient, '+'), 'type' => 'template', 'template' => ['name' => $config['template_name'], 'language' => ['code' => $config['template_language']]]]);

        return $this->accepted($response, 'messages.0.id', null);
    }

    /** @return array{id:string,state:string,segments:int|null} */
    private function accepted(Response $response, string $idPath, ?int $segments): array
    {
        if (! $response->successful() || ! is_string($response->json($idPath))) {
            throw new RuntimeException('The provider did not accept the message.');
        }

        return ['id' => $response->json($idPath), 'state' => 'accepted', 'segments' => $segments];
    }
}
