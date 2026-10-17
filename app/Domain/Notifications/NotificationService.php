<?php

namespace App\Domain\Notifications;

use App\Jobs\SendNotificationEmail;
use App\Jobs\SendNotificationMessage;
use App\Logging\OperationalLogger;
use App\Models\InAppAlert;
use App\Models\Merchant;
use App\Models\MerchantIntegration;
use App\Models\NotificationConsent;
use App\Models\NotificationDelivery;
use App\Models\ReceiptSnapshot;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;

final class NotificationService
{
    public function receiptMessage(User $actor, Merchant $merchant, ReceiptSnapshot $receipt, string $channel, string $phone, string $consentSource, ?string $evidenceReference): NotificationDelivery
    {
        abort_unless($receipt->merchant_id === $merchant->id, 404);
        abort_unless(in_array($channel, ['sms', 'whatsapp'], true), 422, 'Choose SMS or WhatsApp.');
        $phone = $this->normalizePhone($phone);
        $provider = $channel === 'sms' ? 'twilio_sms' : 'meta_whatsapp';
        $integration = MerchantIntegration::query()->where('merchant_id', $merchant->id)->where('provider', $provider)->where('state', 'active')->first();
        if (! $integration instanceof MerchantIntegration) {
            app(OperationalLogger::class)->integration($provider, 'delivery.blocked', 'Receipt delivery was not queued because the channel is inactive.', ['merchant_id' => $merchant->id, 'store_id' => $receipt->store_id, 'subject' => 'receipt_snapshot:'.$receipt->public_id, 'status' => 'skipped', 'context' => ['reason_code' => 'integration_inactive']]);
        }
        abort_unless($integration instanceof MerchantIntegration, 422, ucfirst($channel).' is not active for this merchant.');
        NotificationConsent::query()->updateOrCreate(['merchant_id' => $merchant->id, 'normalized_phone' => $phone, 'channel' => $channel, 'purpose' => 'transactional'], ['recorded_by_user_id' => $actor->id, 'state' => 'granted', 'source' => $consentSource, 'evidence_reference' => $evidenceReference, 'recorded_at' => now()]);
        $dedup = hash('sha256', implode("\x1f", ['receipt', $receipt->public_id, $channel, $phone]));
        try {
            $delivery = NotificationDelivery::query()->firstOrCreate(['merchant_id' => $merchant->id, 'channel' => $channel, 'deduplication_key' => $dedup], ['store_id' => $receipt->store_id, 'requested_by_user_id' => $actor->id, 'merchant_integration_id' => $integration->id, 'message_class' => 'transactional', 'template_key' => 'receipt', 'source_type' => 'receipt_snapshot', 'source_reference' => $receipt->public_id, 'recipient' => $phone, 'state' => 'queued']);
        } catch (QueryException) {
            $delivery = NotificationDelivery::query()->where('merchant_id', $merchant->id)->where('channel', $channel)->where('deduplication_key', $dedup)->firstOrFail();
        }
        if ($delivery->wasRecentlyCreated || $delivery->state === 'failed') {
            $delivery->update(['state' => 'queued', 'failed_at' => null]);
            Context::scope(fn () => Bus::dispatch(new SendNotificationMessage($delivery->id)), hidden: ['log_scope' => $delivery->logContext()]);
        }

        return $delivery->load('attempts');
    }

    public function optOut(User $actor, Merchant $merchant, string $channel, string $phone): NotificationConsent
    {
        $phone = $this->normalizePhone($phone);

        return NotificationConsent::query()->updateOrCreate(['merchant_id' => $merchant->id, 'normalized_phone' => $phone, 'channel' => $channel, 'purpose' => 'transactional'], ['recorded_by_user_id' => $actor->id, 'state' => 'opted_out', 'source' => 'staff_request', 'recorded_at' => now()]);
    }

    public function receipt(User $actor, Merchant $merchant, ReceiptSnapshot $receipt, string $recipient): NotificationDelivery
    {
        abort_unless($receipt->merchant_id === $merchant->id, 404);
        $recipient = mb_strtolower(trim($recipient));
        abort_unless(filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false, 422, 'Enter a valid receipt email address.');
        $dedup = hash('sha256', 'receipt'."\x1f".$receipt->public_id."\x1f".$recipient);
        try {
            $delivery = NotificationDelivery::query()->firstOrCreate(['merchant_id' => $merchant->id, 'channel' => 'email', 'deduplication_key' => $dedup], ['store_id' => $receipt->store_id, 'requested_by_user_id' => $actor->id, 'template_key' => 'receipt', 'source_type' => 'receipt_snapshot', 'source_reference' => $receipt->public_id, 'recipient' => $recipient, 'state' => 'queued']);
        } catch (QueryException) {
            $delivery = NotificationDelivery::query()->where('merchant_id', $merchant->id)->where('channel', 'email')->where('deduplication_key', $dedup)->firstOrFail();
        }
        $shouldDispatch = $delivery->wasRecentlyCreated || $delivery->state === 'failed';
        if ($shouldDispatch) {
            if ($delivery->state === 'failed') {
                $delivery->update(['state' => 'queued']);
            }
            Context::scope(fn () => Bus::dispatch(new SendNotificationEmail($delivery->id)), hidden: ['log_scope' => $delivery->logContext()]);
        }

        return $delivery->load('attempts');
    }

    public function testEmail(User $actor, Merchant $merchant, string $recipient): NotificationDelivery
    {
        abort_unless(filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false, 422, 'Enter a valid test email address.');
        $delivery = NotificationDelivery::query()->create(['merchant_id' => $merchant->id, 'requested_by_user_id' => $actor->id, 'channel' => 'email', 'template_key' => 'settings_test', 'source_type' => 'merchant_email_setting', 'source_reference' => $merchant->public_id, 'recipient' => mb_strtolower($recipient), 'deduplication_key' => hash('sha256', 'test'."\x1f".$merchant->public_id."\x1f".$recipient."\x1f".now()->format('YmdHi')), 'state' => 'queued']);
        Context::scope(fn () => Bus::dispatch(new SendNotificationEmail($delivery->id)), hidden: ['log_scope' => $delivery->logContext()]);

        return $delivery;
    }

    public function alert(Merchant $merchant, ?Store $store, string $category, string $severity, string $title, string $message, string $sourceType, string $sourceReference, string $audienceRole = 'manager'): InAppAlert
    {
        abort_unless($store === null || $store->merchant_id === $merchant->id, 404);
        if ($category === 'payroll') {
            $title = 'Payroll workflow needs attention';
            $message = 'Open the restricted payroll workspace to review the issue.';
            $audienceRole = 'owner';
        }
        $dedup = hash('sha256', implode("\x1f", [$category, $sourceType, $sourceReference, (string) $store?->id]));

        return InAppAlert::query()->firstOrCreate(['merchant_id' => $merchant->id, 'deduplication_key' => $dedup], ['store_id' => $store?->id, 'audience_role' => $audienceRole, 'category' => $category, 'severity' => $severity, 'title' => $title, 'message' => $message, 'source_type' => $sourceType, 'source_reference' => $sourceReference]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));
        abort_unless(is_string($digits), 422, 'Enter a valid mobile number.');
        if (str_starts_with($digits, '0')) {
            $digits = '92'.substr($digits, 1);
        }
        abort_unless(preg_match('/^[1-9]\d{7,14}$/', $digits) === 1, 422, 'Enter a valid mobile number with country code.');

        return '+'.$digits;
    }
}
