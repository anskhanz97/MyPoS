<?php

namespace Tests\Feature;

use App\Domain\Integrations\MessageTransport;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Jobs\SendNotificationMessage;
use App\Models\IntegrationCallback;
use App\Models\Merchant;
use App\Models\MerchantIntegration;
use App\Models\MerchantMembership;
use App\Models\NotificationConsent;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationHubTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_configure_encrypted_tenant_integrations_without_exposing_secrets(): void
    {
        $merchant = Merchant::factory()->create();
        $owner = $this->member($merchant, MembershipRole::Owner);
        $manager = $this->member($merchant, MembershipRole::Manager);
        $url = "/api/v1/merchants/{$merchant->public_id}/integrations/twilio_sms";
        $payload = ['mode' => 'sandbox', 'state' => 'active', 'config' => ['from_number' => '+15005550006', 'messaging_service_sid' => '', 'daily_limit' => '25'], 'credentials' => ['account_sid' => 'AC123456789', 'auth_token' => 'super-secret-token']];

        $this->actingAs($manager)->putJson($url, $payload)->assertForbidden();
        $response = $this->actingAs($owner)->putJson($url, $payload)->assertOk()->assertJsonPath('data.state', 'active')->assertJsonMissing(['super-secret-token'])->json('data');
        $this->assertSame(['account_sid', 'auth_token'], $response['configured_credentials']);
        $stored = MerchantIntegration::query()->where('merchant_id', $merchant->id)->where('provider', 'twilio_sms')->firstOrFail();
        $this->assertSame('super-secret-token', $stored->credentials['auth_token']);
        $this->assertStringNotContainsString('super-secret-token', (string) DB::table('merchant_integrations')->where('id', $stored->id)->value('credentials'));

        $this->actingAs($owner)->getJson("/api/v1/merchants/{$merchant->public_id}/integrations")->assertOk()->assertJsonMissing(['super-secret-token']);
        $this->actingAs($owner)->postJson("/api/v1/merchants/{$merchant->public_id}/integrations/{$stored->public_id}/test")->assertOk()->assertJsonPath('data.state', 'passed')->assertJsonPath('data.message', 'Sandbox configuration is ready. No external request was made.');
        Http::assertNothingSent();

        $other = Merchant::factory()->create();
        $otherOwner = $this->member($other, MembershipRole::Owner);
        $this->actingAs($otherOwner)->postJson("/api/v1/merchants/{$other->public_id}/integrations/{$stored->public_id}/test")->assertNotFound();
    }

    public function test_activation_requires_complete_configuration_and_preserves_saved_credentials(): void
    {
        $merchant = Merchant::factory()->create();
        $owner = $this->member($merchant, MembershipRole::Owner);
        $url = "/api/v1/merchants/{$merchant->public_id}/integrations/meta_whatsapp";
        $this->actingAs($owner)->putJson($url, ['mode' => 'sandbox', 'state' => 'active', 'config' => [], 'credentials' => []])->assertUnprocessable();

        $first = $this->actingAs($owner)->putJson($url, ['mode' => 'sandbox', 'state' => 'draft', 'config' => ['phone_number_id' => '123', 'api_version' => 'v23.0', 'template_name' => 'receipt_ready', 'template_language' => 'en_US', 'daily_limit' => '100'], 'credentials' => ['access_token' => 'token-1', 'app_secret' => 'secret-1', 'verify_token' => 'verify-1']])->assertOk()->json('data');
        $this->actingAs($owner)->putJson($url, ['mode' => 'sandbox', 'state' => 'active', 'version' => $first['version'], 'config' => ['phone_number_id' => '123', 'api_version' => 'v23.0', 'template_name' => 'receipt_ready', 'template_language' => 'en_US', 'daily_limit' => '100'], 'credentials' => []])->assertOk()->assertJsonPath('data.state', 'active');
        $this->assertSame('token-1', MerchantIntegration::query()->where('merchant_id', $merchant->id)->firstOrFail()->credentials['access_token']);
        $this->actingAs($owner)->putJson($url, ['mode' => 'sandbox', 'state' => 'active', 'version' => 1, 'config' => [], 'credentials' => []])->assertConflict();
    }

    public function test_sandbox_messages_require_consent_respect_disable_and_apply_deduplicated_callbacks(): void
    {
        Http::preventStrayRequests();
        $merchant = Merchant::factory()->create();
        $owner = $this->member($merchant, MembershipRole::Owner);
        $integration = MerchantIntegration::factory()->create(['merchant_id' => $merchant->id, 'state' => 'active', 'config' => ['from_number' => '+15005550006', 'daily_limit' => 2], 'credentials' => ['account_sid' => 'AC123', 'auth_token' => 'auth-token']]);
        NotificationConsent::query()->create(['merchant_id' => $merchant->id, 'recorded_by_user_id' => $owner->id, 'normalized_phone' => '+923124246916', 'channel' => 'sms', 'purpose' => 'transactional', 'state' => 'granted', 'source' => 'customer_request', 'recorded_at' => now()]);
        $delivery = $this->delivery($merchant, $owner, $integration, 'receipt-1');
        (new SendNotificationMessage($delivery->id))->handle(app(MessageTransport::class));
        $this->assertSame('accepted', $delivery->refresh()->state);
        $this->assertStringStartsWith('sandbox-', (string) $delivery->provider_message_id);
        Http::assertNothingSent();

        $queued = $this->delivery($merchant, $owner, $integration, 'receipt-2');
        $integration->update(['state' => 'disabled']);
        (new SendNotificationMessage($queued->id))->handle(app(MessageTransport::class));
        $this->assertSame('cancelled', $queued->refresh()->state);

        $integration->update(['state' => 'active', 'mode' => 'live']);
        $delivery->update(['provider_message_id' => 'SM123']);
        $path = "/api/v1/integrations/{$integration->public_id}/callbacks/twilio";
        $payload = ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered', 'NumSegments' => '1', 'Price' => '-0.0075', 'PriceUnit' => 'USD'];
        $this->postJson($path, $payload, ['X-Twilio-Signature' => 'invalid'])->assertForbidden();
        $parameterString = 'MessageSidSM123MessageStatusdeliveredNumSegments1Price-0.0075PriceUnitUSD';
        $signature = base64_encode(hash_hmac('sha1', url($path).$parameterString, 'auth-token', true));
        $this->post($path, $payload, ['X-Twilio-Signature' => $signature])->assertOk();
        $this->post($path, $payload, ['X-Twilio-Signature' => $signature])->assertOk();
        $this->assertSame(1, IntegrationCallback::query()->count());
        $this->assertSame('delivered', $delivery->refresh()->state);
        $this->assertSame(1, $delivery->segment_count);
    }

    private function member(Merchant $merchant, MembershipRole $role): User
    {
        $user = User::factory()->create();
        MerchantMembership::factory()->for($merchant)->for($user)->create(['role' => $role]);

        return $user;
    }

    private function delivery(Merchant $merchant, User $user, MerchantIntegration $integration, string $reference): NotificationDelivery
    {
        return NotificationDelivery::query()->create(['merchant_id' => $merchant->id, 'requested_by_user_id' => $user->id, 'merchant_integration_id' => $integration->id, 'channel' => 'sms', 'message_class' => 'transactional', 'template_key' => 'receipt', 'source_type' => 'receipt_snapshot', 'source_reference' => $reference, 'recipient' => '+923124246916', 'deduplication_key' => hash('sha256', $reference), 'state' => 'queued']);
    }
}
