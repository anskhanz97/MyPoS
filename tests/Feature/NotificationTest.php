<?php

namespace Tests\Feature;

use App\Domain\Notifications\NotificationService;
use App\Domain\Printing\ReceiptPdf;
use App\Jobs\SendNotificationEmail;
use App\Mail\ReceiptEmail;
use App\Models\InAppAlert;
use App\Models\InventoryLocation;
use App\Models\MerchantEmailSetting;
use App\Models\NotificationDelivery;
use App\Models\ReceiptSnapshot;
use App\Models\Register;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_receipt_email_outbox_alert_privacy_and_retry_behaviour(): void
    {
        Queue::fake();
        Mail::fake();
        $fixture = $this->saleFixture();
        $merchant = $fixture['merchant']->public_id;
        $settings = "/api/v1/merchants/{$merchant}/email-settings";
        $this->actingAs($fixture['manager'])->getJson($settings)->assertForbidden();
        $this->actingAs($fixture['owner'])->putJson($settings, ['enabled' => true, 'from_address' => 'receipts@example.test', 'from_name' => 'Example Retail'])->assertOk()->assertJsonMissing(['password']);

        $url = "/api/v1/merchants/{$merchant}/receipts/{$fixture['receipt']->public_id}/email";
        $this->actingAs($fixture['cashier'])->postJson($url, ['email' => 'customer@example.test'])->assertAccepted()->assertJsonPath('data.state', 'queued');
        $this->actingAs($fixture['cashier'])->postJson($url, ['email' => 'customer@example.test'])->assertAccepted();
        $this->actingAs($fixture['cashier'])->postJson($url, ['email' => 'invalid'])->assertUnprocessable();
        $this->assertSame(1, NotificationDelivery::query()->where('template_key', 'receipt')->count());
        Queue::assertPushed(SendNotificationEmail::class, 1);
        $delivery = NotificationDelivery::query()->where('template_key', 'receipt')->firstOrFail();
        (new SendNotificationEmail($delivery->id))->handle(app(ReceiptPdf::class));
        Mail::assertSent(ReceiptEmail::class, 1);
        $this->assertSame('sent', $delivery->refresh()->state);
        $this->assertSame(1, Sale::query()->count());
        (new SendNotificationEmail($delivery->id))->handle(app(ReceiptPdf::class));
        Mail::assertSent(ReceiptEmail::class, 1);

        MerchantEmailSetting::query()->where('merchant_id', $fixture['merchant']->id)->update(['enabled' => false]);
        $test = $this->actingAs($fixture['owner'])->postJson($settings.'/test', ['email' => 'ops@example.test'])->assertAccepted()->json('data');
        $failed = (new NotificationDelivery)->resolveRouteBinding($test['public_id']);
        $this->assertInstanceOf(NotificationDelivery::class, $failed);
        try {
            (new SendNotificationEmail($failed->id))->handle(app(ReceiptPdf::class));
            $this->fail('Disabled email must fail.');
        } catch (RuntimeException) {
        }
        $this->assertSame('failed', $failed->refresh()->state);
        $this->assertSame('configuration', $failed->failure_code);
        MerchantEmailSetting::query()->where('merchant_id', $fixture['merchant']->id)->update(['enabled' => true]);
        (new SendNotificationEmail($failed->id))->handle(app(ReceiptPdf::class));
        $this->assertSame('sent', $failed->refresh()->state);

        $service = app(NotificationService::class);
        $alert = $service->alert($fixture['merchant'], $fixture['store'], 'inventory_expiry', 'warning', 'Batch expires soon', 'Review TEA batch', 'product_batch', 'B-1');
        $service->alert($fixture['merchant'], $fixture['store'], 'inventory_expiry', 'warning', 'Duplicate', 'Duplicate', 'product_batch', 'B-1');
        $payroll = $service->alert($fixture['merchant'], null, 'payroll', 'danger', 'PKR 75,000 failed', 'Ayesha PKR 75,000', 'payroll_run', 'RUN-1');
        $this->assertSame(2, InAppAlert::query()->count());
        $this->assertSame('owner', $payroll->audience_role);
        $this->assertStringNotContainsString('75,000', $payroll->message);
        $alerts = "/api/v1/merchants/{$merchant}/alerts?store_id={$fixture['store']->public_id}";
        $this->actingAs($fixture['manager'])->getJson($alerts)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_read', false);
        $this->actingAs($fixture['cashier'])->getJson($alerts)->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($fixture['manager'])->postJson("/api/v1/merchants/{$merchant}/alerts/{$alert->public_id}/read")->assertOk();
        $this->actingAs($fixture['manager'])->getJson($alerts.'&unread_only=1')->assertOk()->assertJsonCount(0, 'data');
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson($url, ['email' => 'x@example.test'])->assertNotFound();
    }

    /** @return array<string,mixed> */
    private function saleFixture(): array
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '1000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cart = $this->actingAs($fixture['cashier'])->postJson($base.'/carts', ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $this->actingAs($fixture['cashier'])->putJson($base.'/carts/'.$cart['id'], ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($base.'/carts/'.$cart['id'].'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '118.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'])->assertCreated();

        return [...$fixture, 'receipt' => ReceiptSnapshot::query()->firstOrFail()];
    }
}
