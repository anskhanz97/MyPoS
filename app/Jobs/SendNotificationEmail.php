<?php

namespace App\Jobs;

use App\Domain\Printing\ReceiptPdf;
use App\Mail\ReceiptEmail;
use App\Models\MerchantEmailSetting;
use App\Models\MerchantIntegration;
use App\Models\NotificationAttempt;
use App\Models\NotificationDelivery;
use App\Models\ReceiptSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 90, 300];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(ReceiptPdf $pdf): void
    {
        $delivery = DB::transaction(function (): ?NotificationDelivery {
            $locked = NotificationDelivery::query()->lockForUpdate()->findOrFail($this->deliveryId);
            if (in_array($locked->state, ['sent', 'sending'], true)) {
                return null;
            }
            $locked->update(['state' => 'sending', 'attempt_count' => $locked->attempt_count + 1, 'failure_code' => null, 'safe_failure_message' => null]);

            return $locked->refresh();
        });
        if (! $delivery instanceof NotificationDelivery) {
            return;
        }
        try {
            $setting = MerchantEmailSetting::query()->where('merchant_id', $delivery->merchant_id)->first();
            if (! $setting instanceof MerchantEmailSetting || ! $setting->enabled) {
                throw new RuntimeException('Merchant email is disabled.');
            }
            $integration = MerchantIntegration::query()->where('merchant_id', $delivery->merchant_id)->where('provider', 'smtp')->where('state', 'active')->first();
            if ($integration instanceof MerchantIntegration) {
                $smtp = (array) $integration->config;
                $credentials = (array) $integration->credentials;
                config(['mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => ($smtp['encryption'] ?? '') === 'ssl' ? 'smtps' : null, 'url' => null, 'host' => $smtp['host'], 'port' => (int) $smtp['port'], 'username' => $credentials['username'], 'password' => $credentials['password'], 'timeout' => 10, 'local_domain' => null]]);
                Mail::purge('smtp');
                if ($integration->mode === 'sandbox') {
                    $providerId = 'sandbox-'.Str::uuid();
                    NotificationAttempt::query()->create(['notification_delivery_id' => $delivery->id, 'attempt_number' => $delivery->attempt_count, 'outcome' => 'accepted', 'provider_message_id' => $providerId, 'attempted_at' => now()]);
                    $delivery->update(['state' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now()]);

                    return;
                }
            }
            if ($delivery->template_key === 'receipt') {
                $receipt = (new ReceiptSnapshot)->resolveRouteBinding($delivery->source_reference);
                if (! $receipt instanceof ReceiptSnapshot || $receipt->merchant_id !== $delivery->merchant_id) {
                    throw new RuntimeException('Receipt snapshot is unavailable.');
                }
                ($integration instanceof MerchantIntegration ? Mail::mailer('smtp') : Mail::mailer())->to($delivery->recipient)->send((new ReceiptEmail($receipt->payload, $pdf->render($receipt)))->from($setting->from_address, $setting->from_name));
            } else {
                ($integration instanceof MerchantIntegration ? Mail::mailer('smtp') : Mail::mailer())->raw('MyPoS email delivery test. No customer data is included.', fn ($message) => $message->to($delivery->recipient)->from($setting->from_address, $setting->from_name)->subject('MyPoS email test'));
            }
            $providerId = 'local-'.Str::uuid();
            NotificationAttempt::query()->create(['notification_delivery_id' => $delivery->id, 'attempt_number' => $delivery->attempt_count, 'outcome' => 'accepted', 'provider_message_id' => $providerId, 'attempted_at' => now()]);
            $delivery->update(['state' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now()]);
            if ($integration instanceof MerchantIntegration) {
                Mail::purge('smtp');
            }
        } catch (Throwable $e) {
            if (($integration ?? null) instanceof MerchantIntegration) {
                Mail::purge('smtp');
            }
            $code = $e instanceof RuntimeException ? 'configuration' : 'provider_error';
            NotificationAttempt::query()->create(['notification_delivery_id' => $delivery->id, 'attempt_number' => $delivery->attempt_count, 'outcome' => 'failed', 'failure_code' => $code, 'safe_message' => 'Email delivery was not accepted. Retry after checking configuration.', 'attempted_at' => now()]);
            $delivery->update(['state' => 'failed', 'failure_code' => $code, 'safe_failure_message' => 'Email delivery was not accepted. Retry after checking configuration.']);
            throw $e;
        }
    }
}
