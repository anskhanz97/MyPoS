<?php

namespace Tests\Feature;

use App\Domain\Sales\PostSaleReturn;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class SaleReturnConcurrencyTest extends TestCase
{
    use BuildsPricingFixture, DatabaseTruncation;

    public function test_parallel_distinct_returns_cannot_both_consume_the_same_remaining_quantity(): void
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '1000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cart = $this->actingAs($fixture['cashier'])->postJson($base.'/carts', ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $detail = $base.'/carts/'.$cart['id'];
        $this->actingAs($fixture['cashier'])->putJson($detail, ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($detail.'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $sale = $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => 1, 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '118.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'])->assertCreated()->json('data');
        $identity = ['merchant' => $fixture['merchant']->public_id, 'store' => $fixture['store']->public_id, 'register' => $register->public_id, 'sale' => $sale['id'], 'actor' => $fixture['cashier']->id];
        $commands = [];
        foreach ([1, 2] as $attempt) {
            $commands[] = ['key' => (string) Str::uuid(), 'data' => ['register_session_id' => $session['id'], 'reason' => 'Concurrent return attempt', 'lines' => [['sale_line_id' => $sale['lines'][0]['id'], 'quantity' => '1.000000', 'disposition' => 'discard']], 'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '118.00', 'confirmation_source' => 'not_applicable']]]];
        }

        $tasks = array_map(static function (array $command) use ($identity): \Closure {
            return static function () use ($command, $identity): string {
                $merchant = (new Merchant)->resolveRouteBinding($identity['merchant']);
                $store = (new Store)->resolveRouteBinding($identity['store']);
                $register = (new Register)->resolveRouteBinding($identity['register']);
                $sale = (new Sale)->resolveRouteBinding($identity['sale']);
                $actor = User::query()->find($identity['actor']);
                if (! $merchant instanceof Merchant || ! $store instanceof Store || ! $register instanceof Register || ! $sale instanceof Sale || ! $actor instanceof User) {
                    throw new \RuntimeException('Concurrent return fixture could not be resolved.');
                }
                try {
                    app(PostSaleReturn::class)->handle($merchant, $store, $register, $sale, $actor, $command['key'], $command['data']);

                    return 'posted';
                } catch (HttpExceptionInterface $exception) {
                    return $exception->getStatusCode() === 409 ? 'conflict' : 'unexpected-'.$exception->getStatusCode();
                }
            };
        }, $commands);

        $results = Concurrency::run($tasks, timeout: 90);
        sort($results);
        $this->assertSame(['conflict', 'posted'], $results);
        $this->assertSame(1, SaleReturn::query()->count());
        $this->assertSame('fully_returned', Sale::query()->firstOrFail()->state);
    }
}
