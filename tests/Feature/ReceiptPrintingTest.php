<?php

namespace Tests\Feature;

use App\Domain\Offline\CanonicalPayload;
use App\Models\DeviceEnrollment;
use App\Models\DrawerCommand;
use App\Models\InventoryLocation;
use App\Models\PrintAttempt;
use App\Models\PrintJob;
use App\Models\ReceiptSnapshot;
use App\Models\Register;
use App\Models\Sale;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class ReceiptPrintingTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_original_and_reprint_jobs_are_frozen_signed_and_idempotent(): void
    {
        $fixture = $this->saleFixture();
        $key = (string) Str::uuid();
        $url = "/api/v1/receipts/{$fixture['receipt']->public_id}/print-jobs";
        $payload = ['register_id' => $fixture['register']->public_id, 'copy_type' => 'original', 'printer_name' => 'Counter 1'];

        $created = $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($url, $payload)
            ->assertCreated()->assertJsonPath('data.copy_type', 'original')->assertJsonPath('data.state', 'created')
            ->assertJsonPath('data.signed_authorization.algorithm', 'Ed25519')->assertJsonPath('data.drawer_command.kind', 'sale_cash');
        $job = PrintJob::query()->firstOrFail();
        $authorization = $job->signed_authorization;
        $this->assertTrue(sodium_crypto_sign_verify_detached(
            base64_decode((string) $authorization['signature'], true),
            app(CanonicalPayload::class)->json($authorization['payload']),
            base64_decode((string) $authorization['public_key'], true),
        ));
        $this->assertStringContainsString('Tea', json_encode($job->render_payload, JSON_THROW_ON_ERROR));
        $this->assertSame(1, DrawerCommand::query()->where('print_job_id', $job->id)->count());

        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($url, $payload)
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $created->json('data.id'));
        $changed = $payload;
        $changed['printer_name'] = 'Counter 2';
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($url, $changed)->assertConflict();
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, $payload)->assertConflict();
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, ['register_id' => $fixture['register']->public_id, 'copy_type' => 'reprint'])->assertUnprocessable();

        $fixture['first']->product->update(['name' => 'Renamed after sale']);
        $reprint = $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, ['register_id' => $fixture['register']->public_id, 'copy_type' => 'reprint', 'reason' => 'Customer requested another copy'])
            ->assertCreated()->assertJsonPath('data.drawer_command', null);
        $this->assertStringContainsString('COPY / REPRINT', json_encode($reprint->json('data.render_payload'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->assertStringContainsString('Tea', json_encode($reprint->json('data.render_payload'), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Renamed after sale', json_encode($reprint->json('data.render_payload'), JSON_THROW_ON_ERROR));
        $this->actingAs($fixture['cashier'])->get("/api/v1/receipts/{$fixture['receipt']->public_id}/pdf?copy_type=reprint")
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertSee('%PDF-', false);
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_helper_outcomes_and_drawer_outcomes_are_separately_replay_safe(): void
    {
        $fixture = $this->saleFixture();
        $job = $this->createPrintJob($fixture);
        $device = DeviceEnrollment::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'register_id' => $fixture['register']->id]);
        $attemptUrl = "/api/v1/print-jobs/{$job->public_id}/attempts";
        $accepted = $this->attemptPayload($device, 1, 'accepted_by_helper');
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($attemptUrl, $accepted)->assertCreated()->assertJsonPath('data.state', 'accepted_by_helper');
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($attemptUrl, $accepted)->assertOk()->assertJsonPath('replayed', true);
        $altered = $accepted;
        $altered['outcome'] = 'failed';
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($attemptUrl, $altered)->assertConflict();
        $duplicateNumber = $this->attemptPayload($device, 1, 'failed');
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($attemptUrl, $duplicateNumber)->assertConflict();
        $printed = $this->attemptPayload($device, 2, 'printed_reported');
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($attemptUrl, $printed)->assertCreated()->assertJsonPath('data.state', 'printed_reported');
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($attemptUrl, $this->attemptPayload($device, 3, 'failed'))->assertConflict();

        $drawer = DrawerCommand::query()->where('print_job_id', $job->id)->firstOrFail();
        $outcome = ['device_id' => $device->public_id, 'helper_event_id' => (string) Str::uuid(), 'outcome' => 'opened_reported', 'reported_at' => now()->toAtomString()];
        $drawerUrl = "/api/v1/drawer-commands/{$drawer->public_id}/outcomes";
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($drawerUrl, $outcome)->assertCreated()->assertJsonPath('data.state', 'opened_reported');
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($drawerUrl, $outcome)->assertOk()->assertJsonPath('replayed', true);
        $changedTimestamp = $outcome;
        $changedTimestamp['reported_at'] = now()->addSecond()->toAtomString();
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($drawerUrl, $changedTimestamp)->assertConflict();
        $changed = $outcome;
        $changed['outcome'] = 'failed';
        $this->withHeader('X-Device-Key', 'test-device-key')->postJson($drawerUrl, $changed)->assertConflict();
        $this->assertSame(2, PrintAttempt::query()->count());
        $this->assertSame(1, Sale::query()->count(), 'Printing outcomes must never duplicate or roll back the sale.');
    }

    public function test_manual_drawer_access_enforces_capability_scope_and_reason(): void
    {
        $fixture = $this->saleFixture();
        $sessionId = $fixture['session']['id'];
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$fixture['register']->public_id}/cash-sessions/{$sessionId}/drawer-commands";
        $payload = ['kind' => 'no_sale_open', 'reason' => 'Manager verified till supplies'];
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, $payload)->assertForbidden();
        $key = (string) Str::uuid();
        $created = $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', $key)->postJson($url, $payload)
            ->assertCreated()->assertJsonPath('data.kind', 'no_sale_open')->assertJsonPath('data.state', 'authorized');
        $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', $key)->postJson($url, $payload)
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $created->json('data.id'));
        $this->actingAs($fixture['manager'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, ['kind' => 'paid_out', 'reason' => 'Petty cash reimbursement'])->assertCreated();

        $other = $this->pricingFixture();
        $this->actingAs($other['owner'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/receipts/{$fixture['receipt']->public_id}/print-jobs", ['register_id' => $fixture['register']->public_id, 'copy_type' => 'original'])->assertNotFound();

        $fixture['register']->update(['capabilities' => ['scanner' => true, 'printer' => false, 'cash_drawer' => true, 'customer_display' => false]]);
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/receipts/{$fixture['receipt']->public_id}/print-jobs", ['register_id' => $fixture['register']->public_id, 'copy_type' => 'original'])->assertConflict();
    }

    /** @return array<string, mixed> */
    private function saleFixture(): array
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'capabilities' => ['scanner' => true, 'printer' => true, 'cash_drawer' => true, 'customer_display' => false]]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '1000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cart = $this->actingAs($fixture['cashier'])->postJson($base.'/carts', ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $this->actingAs($fixture['cashier'])->putJson($base.'/carts/'.$cart['id'], ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($base.'/carts/'.$cart['id'].'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '118.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'])->assertCreated();

        return [...$fixture, 'register' => $register, 'session' => $session, 'receipt' => ReceiptSnapshot::query()->firstOrFail()];
    }

    /** @param array<string, mixed> $fixture */
    private function createPrintJob(array $fixture): PrintJob
    {
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/receipts/{$fixture['receipt']->public_id}/print-jobs", ['register_id' => $fixture['register']->public_id, 'copy_type' => 'original', 'printer_name' => 'Counter 1'])->assertCreated();

        return PrintJob::query()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function attemptPayload(DeviceEnrollment $device, int $number, string $outcome): array
    {
        return ['device_id' => $device->public_id, 'helper_event_id' => (string) Str::uuid(), 'attempt_number' => $number, 'outcome' => $outcome, 'helper_id' => 'local-helper', 'helper_version' => '1.0.0', 'printer_name' => 'Counter 1', 'reported_at' => now()->toAtomString()];
    }
}
