<?php

namespace Tests\Feature;

use App\Domain\Sales\FinalizeSale;
use App\Models\CashMovement;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\ReceiptSnapshot;
use App\Models\Register;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class SaleFinalizationConcurrencyTest extends TestCase
{
    use BuildsPricingFixture, DatabaseTruncation;

    public function test_parallel_identical_finalization_commands_post_every_effect_once(): void
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '100.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cart = $this->actingAs($fixture['cashier'])->postJson($base.'/carts', ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $detail = $base.'/carts/'.$cart['id'];
        $this->actingAs($fixture['cashier'])->putJson($detail, ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($detail.'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $identity = ['merchant' => $fixture['merchant']->public_id, 'store' => $fixture['store']->public_id, 'register' => $register->public_id, 'actor' => $fixture['cashier']->id];
        $key = (string) Str::uuid();
        $data = ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '120.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '2.00'];

        $tasks = [];
        foreach ([1, 2] as $ignored) {
            $tasks[] = static function () use ($identity, $key, $data): string {
                $merchant = (new Merchant)->resolveRouteBinding($identity['merchant']);
                $store = (new Store)->resolveRouteBinding($identity['store']);
                $register = (new Register)->resolveRouteBinding($identity['register']);
                $actor = User::query()->find($identity['actor']);
                if (! $merchant instanceof Merchant || ! $store instanceof Store || ! $register instanceof Register || ! $actor instanceof User) {
                    throw new \RuntimeException('Concurrent sale fixture could not be resolved.');
                }

                $result = app(FinalizeSale::class)->handle($merchant, $store, $register, $actor, $key, $data);

                return $result['replayed'] ? 'replayed' : 'posted';
            };
        }

        $results = Concurrency::run($tasks, timeout: 60);
        sort($results);
        $this->assertSame(['posted', 'replayed'], $results);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, ReceiptSnapshot::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_kind', 'sale')->count());
        $this->assertSame(1, CashMovement::query()->where('kind', 'sale_cash')->count());
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'sales.sale.finalized')->count());
    }
}
