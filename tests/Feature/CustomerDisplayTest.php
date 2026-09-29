<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CustomerDisplayPairing;
use App\Models\CustomerDisplaySnapshot;
use App\Models\InventoryLocation;
use App\Models\Register;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class CustomerDisplayTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_pairing_is_one_time_register_scoped_and_revocable(): void
    {
        $fixture = $this->displayFixture();
        $url = $this->pairingUrl($fixture);
        $issued = $this->actingAs($fixture['cashier'])->postJson($url)->assertCreated()
            ->assertJsonMissingPath('data.display_token')->json('data');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $issued['pairing_code']);
        $this->assertNotSame($issued['pairing_code'], CustomerDisplayPairing::query()->firstOrFail()->code_hash);

        $wrong = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'capabilities' => $this->capabilities(true)]);
        $this->postJson('/api/v1/customer-display-pairings/consume', ['register_id' => $wrong->public_id, 'pairing_code' => $issued['pairing_code']])->assertNotFound();
        $consumed = $this->postJson('/api/v1/customer-display-pairings/consume', ['register_id' => $fixture['register']->public_id, 'pairing_code' => $issued['pairing_code']])->assertCreated()->json('data');
        $this->assertNotEmpty($consumed['display_token']);
        $this->postJson('/api/v1/customer-display-pairings/consume', ['register_id' => $fixture['register']->public_id, 'pairing_code' => $issued['pairing_code']])->assertConflict();

        $projectionUrl = "/api/v1/customer-display-pairings/{$issued['pairing_id']}/projection";
        $this->withHeader('X-Display-Token', 'wrong')->getJson($projectionUrl)->assertUnauthorized();
        $this->withHeader('X-Display-Token', $consumed['display_token'])->getJson($projectionUrl)->assertOk()->assertJsonPath('data.state', 'idle')->assertJsonPath('data.payload.message', 'Welcome');
        $this->actingAs($fixture['cashier'])->deleteJson($url.'/'.$issued['pairing_id'])->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->withHeader('X-Display-Token', $consumed['display_token'])->getJson($projectionUrl)->assertUnauthorized();
    }

    public function test_projection_is_derived_minimal_read_only_and_clears_after_completion(): void
    {
        $fixture = $this->displayFixture();
        $pairing = $this->pair($fixture);
        $cartUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$fixture['register']->public_id}/carts";
        $cart = $this->actingAs($fixture['cashier'])->postJson($cartUrl, ['inventory_location_id' => $fixture['location']->public_id])->assertCreated()->json('data');
        $detail = $cartUrl.'/'.$cart['id'];
        $this->actingAs($fixture['cashier'])->putJson($detail, ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '2.000000']]])->assertOk();
        $this->actingAs($fixture['cashier'])->postJson($detail.'/quotes', ['expected_version' => 2])->assertCreated();
        $publishUrl = $this->pairingUrl($fixture).'/'.$pairing['pairing_id'].'/projection';
        $published = $this->actingAs($fixture['cashier'])->postJson($publishUrl, ['state' => 'cart', 'cart_id' => $cart['id']])->assertOk()
            ->assertJsonPath('data.payload.lines.0.name', fn (string $name): bool => str_starts_with($name, 'Tea '))
            ->assertJsonPath('data.payload.totals.gross', '236.00');
        $json = json_encode($published->json('data.payload'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('email', mb_strtolower($json));
        $this->assertStringNotContainsString('phone', mb_strtolower($json));
        $this->assertStringNotContainsString('customer', mb_strtolower($json));
        $sequence = (int) $published->json('data.sequence');

        $projectionUrl = "/api/v1/customer-display-pairings/{$pairing['pairing_id']}/projection";
        $this->withHeader('X-Display-Token', $pairing['display_token'])->getJson($projectionUrl)->assertOk()->assertJsonPath('data.sequence', $sequence)->assertJsonPath('data.payload.totals.gross', '236.00');
        $this->withHeader('X-Display-Token', $pairing['display_token'])->postJson($projectionUrl, ['state' => 'cancelled'])->assertMethodNotAllowed();

        $sale = $this->finalize($fixture, Cart::query()->firstOrFail());
        $completedAt = CarbonImmutable::parse('2026-09-29T10:00:00+05:00');
        CarbonImmutable::setTestNow($completedAt);
        $this->actingAs($fixture['cashier'])->postJson($publishUrl, ['state' => 'completed', 'sale_id' => $sale->public_id])->assertOk()->assertJsonPath('data.payload.message', 'Thank you. Payment complete.')->assertJsonMissingPath('data.payload.tenders');
        CarbonImmutable::setTestNow($completedAt->addSeconds(31));
        $this->withHeader('X-Display-Token', $pairing['display_token'])->getJson($projectionUrl)->assertOk()->assertJsonPath('data.state', 'idle')->assertJsonPath('data.payload.lines', []);
        CarbonImmutable::setTestNow();
        $this->assertSame('idle', CustomerDisplaySnapshot::query()->firstOrFail()->state);
    }

    public function test_disabled_capability_and_cross_tenant_projection_are_rejected(): void
    {
        $fixture = $this->displayFixture(false);
        $this->actingAs($fixture['cashier'])->postJson($this->pairingUrl($fixture))->assertConflict();
        $fixture['register']->update(['capabilities' => $this->capabilities(true)]);
        $pairing = $this->pair($fixture);
        $other = $this->pricingFixture();
        $otherRegister = Register::factory()->create(['merchant_id' => $other['merchant']->id, 'store_id' => $other['store']->id, 'capabilities' => $this->capabilities(true)]);
        $url = "/api/v1/merchants/{$other['merchant']->public_id}/stores/{$other['store']->public_id}/registers/{$otherRegister->public_id}/customer-display-pairings/{$pairing['pairing_id']}/projection";
        $this->actingAs($other['cashier'])->postJson($url, ['state' => 'idle'])->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function displayFixture(bool $enabled = true): array
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'capabilities' => $this->capabilities($enabled)]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();

        return [...$fixture, 'register' => $register, 'location' => $location];
    }

    /** @param array<string, mixed> $fixture @return array<string, mixed> */
    private function pair(array $fixture): array
    {
        $issued = $this->actingAs($fixture['cashier'])->postJson($this->pairingUrl($fixture))->assertCreated()->json('data');
        $consumed = $this->postJson('/api/v1/customer-display-pairings/consume', ['register_id' => $fixture['register']->public_id, 'pairing_code' => $issued['pairing_code']])->assertCreated()->json('data');

        return [...$issued, ...$consumed];
    }

    /** @param array<string, mixed> $fixture */
    private function pairingUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$fixture['register']->public_id}/customer-display-pairings";
    }

    /** @return array{scanner: bool, printer: bool, cash_drawer: bool, customer_display: bool} */
    private function capabilities(bool $display): array
    {
        return ['scanner' => true, 'printer' => false, 'cash_drawer' => false, 'customer_display' => $display];
    }

    /** @param array<string, mixed> $fixture */
    private function finalize(array $fixture, Cart $cart): Sale
    {
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$fixture['register']->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '0.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $quote = $cart->quotes()->latest('quote_version')->firstOrFail();
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote->public_id, 'quote_version' => $quote->quote_version, 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '236.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'])->assertCreated();

        return Sale::query()->firstOrFail();
    }
}
