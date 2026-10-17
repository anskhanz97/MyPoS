<?php

namespace Tests\Feature;

use App\Domain\Auth\Actions\IssueApproval;
use App\Domain\Auth\Actions\SwitchDeviceStaff;
use App\Domain\Auth\Enums\Permission;
use App\Domain\Shared\Math\Decimal;
use App\Models\Approval;
use App\Models\CashMovement;
use App\Models\DeviceEnrollment;
use App\Models\InventoryLocation;
use App\Models\MerchantMembership;
use App\Models\OutboxEvent;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\SaleReturnTender;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_linked_partial_returns_preserve_original_amounts_restock_and_replay_once(): void
    {
        $context = $this->soldItem('1.000000');
        $repriced = $this->pricingPayload($context['fixture'], ['expected_version' => 1, 'prices' => [
            ['variant_id' => $context['fixture']['first']->public_id, 'unit_price' => '200.0000', 'tax_rate_percent' => '18.000000', 'tax_inclusive' => false],
            ['variant_id' => $context['fixture']['second']->public_id, 'unit_price' => '50.0000', 'tax_rate_percent' => '0', 'tax_inclusive' => false],
        ]]);
        $this->actingAs($context['fixture']['owner'])->postJson($this->pricingUrl($context['fixture']).'/versions', $repriced)->assertCreated();
        $lineId = $context['sale']['lines'][0]['id'];
        $url = $context['base'].'/sales/'.$context['sale']['id'].'/returns';
        $key = (string) Str::uuid();
        $payload = $this->returnPayload($context['session']['id'], $lineId, '0.500000', '59.00');

        $first = $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $key)->postJson($url, $payload)
            ->assertCreated()->assertJsonPath('data.state', 'posted')->assertJsonPath('data.totals.gross', '59.00')
            ->assertJsonPath('data.lines.0.cumulative_quantity', '0.500000')->assertJsonPath('data.lines.0.disposition', 'restock');
        $this->assertSame('partially_returned', Sale::query()->firstOrFail()->state);
        $this->assertSame('0.500000', StockMovement::query()->where('movement_kind', 'return')->value('quantity_base'));
        $this->assertSame('-59.00', CashMovement::query()->where('kind', 'refund_cash')->value('signed_amount'));
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'loyalty.sale.returned')->count());
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'fiscal.sale.returned')->count());

        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $key)->postJson($url, $payload)
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, SaleReturn::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_kind', 'return')->count());

        $payload = $this->returnPayload($context['session']['id'], $lineId, '0.500000', '59.00');
        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, $payload)
            ->assertCreated()->assertJsonPath('data.lines.0.cumulative_quantity', '1.000000');
        $this->assertSame('fully_returned', Sale::query()->firstOrFail()->state);
        $this->assertSame('1.000000', Decimal::fixed(Decimal::of(SaleReturnLine::query()->sum('quantity_uom')), 6));

        $payload = $this->returnPayload($context['session']['id'], $lineId, '0.000001', '0.01');
        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, $payload)->assertConflict();
        $this->assertSame(2, SaleReturn::query()->count());
    }

    public function test_pending_external_refund_is_explicit_and_confirmation_is_replay_safe(): void
    {
        $context = $this->soldItem('1.000000');
        $url = $context['base'].'/sales/'.$context['sale']['id'].'/returns';
        $payload = $this->returnPayload($context['session']['id'], $context['sale']['lines'][0]['id'], '1.000000', '118.00', 'discard');
        $payload['refund_tenders'][0] = ['tender_id' => (string) Str::uuid(), 'type' => 'external_card', 'state' => 'pending_external_action', 'amount' => '118.00'];
        $return = $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, $payload)
            ->assertCreated()->assertJsonPath('data.state', 'refund_pending')->assertJsonPath('data.refund_tenders.0.state', 'pending_external_action')->json('data');
        $this->assertSame(0, StockMovement::query()->where('movement_kind', 'return')->count());
        $this->assertSame(0, CashMovement::query()->where('kind', 'refund_cash')->count());

        $confirmUrl = $context['base'].'/sale-returns/'.$return['id'].'/refund-tenders/'.$return['refund_tenders'][0]['id'].'/confirm';
        $evidence = ['external_reference' => 'CARD-REFUND-9001', 'confirmation_source' => 'staff_declaration'];
        $this->actingAs($context['fixture']['cashier'])->postJson($confirmUrl, $evidence)->assertOk()->assertJsonPath('data.state', 'posted')->assertJsonPath('replayed', false);
        $this->actingAs($context['fixture']['cashier'])->postJson($confirmUrl, $evidence)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, SaleReturnTender::query()->where('state', 'refunded')->count());
    }

    public function test_exchange_posts_return_and_fully_paid_replacement_in_one_transaction_with_difference(): void
    {
        $context = $this->soldItem('1.000000');
        $cart = $this->actingAs($context['fixture']['cashier'])->postJson($context['base'].'/carts', ['inventory_location_id' => $context['location']->public_id])->assertCreated()->json('data');
        $detail = $context['base'].'/carts/'.$cart['id'];
        $this->actingAs($context['fixture']['cashier'])->putJson($detail, ['expected_version' => 1, 'lines' => [['variant_id' => $context['fixture']['first']->public_id, 'quantity' => '2.000000']]])->assertOk();
        $quote = $this->actingAs($context['fixture']['cashier'])->postJson($detail.'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $returnPayload = $this->returnPayload($context['session']['id'], $context['sale']['lines'][0]['id'], '1.000000', '118.00');
        $returnPayload['idempotency_key'] = (string) Str::uuid();
        $newSale = ['idempotency_key' => (string) Str::uuid(), 'client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $context['session']['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '236.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'];
        $exchangeUrl = $context['base'].'/sales/'.$context['sale']['id'].'/exchanges';
        $exchangeKey = (string) Str::uuid();
        $payload = ['return' => $returnPayload, 'new_sale' => $newSale];

        $created = $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $exchangeKey)->postJson($exchangeUrl, $payload)
            ->assertCreated()->assertJsonPath('data.difference.direction', 'customer_pays')->assertJsonPath('data.difference.amount', '118.00')
            ->assertJsonPath('data.return.state', 'posted')->assertJsonPath('data.new_sale.total', '236.00');
        $this->assertSame(2, Sale::query()->count());
        $this->assertSame(1, SaleReturn::query()->count());
        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $exchangeKey)->postJson($exchangeUrl, $payload)
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $created->json('data.id'));
        $this->assertSame(2, Sale::query()->count());
    }

    public function test_late_return_requires_action_bound_manager_approval_and_replay_does_not_reconsume_it(): void
    {
        $context = $this->soldItem('1.000000');
        $sale = Sale::query()->firstOrFail();
        $sale->forceFill(['finalized_at' => now()->subDays(8)])->save();
        $key = (string) Str::uuid();
        $url = $context['base'].'/sales/'.$context['sale']['id'].'/returns';
        $payload = $this->returnPayload($context['session']['id'], $context['sale']['lines'][0]['id'], '1.000000', '118.00', 'discard');
        $payload['late_reason'] = 'Manager accepted sealed goods after the normal deadline';
        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $key)->postJson($url, $payload)->assertConflict();

        $cashierMembership = MerchantMembership::query()->where('merchant_id', $context['fixture']['merchant']->id)->where('user_id', $context['fixture']['cashier']->id)->firstOrFail();
        $managerMembership = MerchantMembership::query()->where('merchant_id', $context['fixture']['merchant']->id)->where('user_id', $context['fixture']['manager']->id)->firstOrFail();
        $cashierMembership->update(['pin_hash' => Hash::make('1357')]);
        $managerMembership->update(['pin_hash' => Hash::make('2468')]);
        $device = DeviceEnrollment::factory()->create(['merchant_id' => $context['fixture']['merchant']->id, 'store_id' => $context['fixture']['store']->id, 'register_id' => Register::query()->firstOrFail()->id, 'device_key_hash' => Hash::make('device-secret')]);
        $staffSession = app(SwitchDeviceStaff::class)->handle($device, $cashierMembership, 'device-secret', '1357')['session'];
        $issued = app(IssueApproval::class)->handle($staffSession, $managerMembership, Permission::ReturnCreate, 'sale_return', $key, $payload['late_reason'], '2468');

        $created = $this->actingAs($context['fixture']['cashier'])->withHeaders(['Idempotency-Key' => $key, 'X-Approval-Token' => $issued['token']])->postJson($url, $payload)
            ->assertCreated()->assertJsonPath('data.late_exception', true)->assertJsonPath('data.policy_window_days', 7);
        $this->assertNotNull(Approval::query()->findOrFail($issued['approval']->id)->consumed_at);
        $this->actingAs($context['fixture']['cashier'])->withHeaders(['Idempotency-Key' => $key, 'X-Approval-Token' => $issued['token']])->postJson($url, $payload)
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $created->json('data.id'));
    }

    /** @return array{fixture: array<string,mixed>, base: string, location: InventoryLocation, session: array<string,mixed>, sale: array<string,mixed>} */
    private function soldItem(string $quantity): array
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '1000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cart = $this->actingAs($fixture['cashier'])->postJson($base.'/carts', ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $detail = $base.'/carts/'.$cart['id'];
        $this->actingAs($fixture['cashier'])->putJson($detail, ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => $quantity]]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($detail.'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $sale = $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => $quote['result']['totals']['gross'], 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'])->assertCreated()->json('data');

        return compact('fixture', 'base', 'location', 'session', 'sale');
    }

    /** @return array<string,mixed> */
    private function returnPayload(string $sessionId, string $lineId, string $quantity, string $amount, string $disposition = 'restock'): array
    {
        return ['register_session_id' => $sessionId, 'reason' => 'Customer changed their mind', 'lines' => [['sale_line_id' => $lineId, 'quantity' => $quantity, 'disposition' => $disposition]], 'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => $amount, 'confirmation_source' => 'not_applicable']]];
    }
}
