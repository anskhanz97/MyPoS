<?php

namespace Tests\Feature;

use App\Domain\Checkout\Enums\CartStatus;
use App\Domain\Loyalty\LoyaltyService;
use App\Models\Cart;
use App\Models\InventoryLocation;
use App\Models\LoyaltyCustomerLink;
use App\Models\LoyaltyMember;
use App\Models\LoyaltyPointBalance;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\Merchant;
use App\Models\OperationalCustomer;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class LoyaltyConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_parallel_cross_store_reservations_cannot_double_spend_points(): void
    {
        $merchant = Merchant::factory()->independent()->create();
        $member = LoyaltyMember::factory()->create(['merchant_id' => $merchant->id]);
        LoyaltyPointBalance::query()->create(['merchant_id' => $merchant->id, 'loyalty_member_id' => $member->id, 'balance' => '10.0000', 'version' => 1]);
        $program = LoyaltyProgram::query()->create(['merchant_id' => $merchant->id, 'slug' => 'cross-store', 'name' => 'Cross-store', 'is_enabled' => true, 'version' => 1]);
        $reward = LoyaltyReward::query()->create(['merchant_id' => $merchant->id, 'loyalty_program_id' => $program->id, 'name' => 'Reward', 'points_required' => '10.0000', 'discount_amount' => '10.00', 'is_active' => true, 'version' => 1]);
        $user = User::factory()->create();
        $carts = [];
        foreach (range(1, 2) as $sequence) {
            $store = Store::factory()->for($merchant)->create();
            $register = Register::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id]);
            $location = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'code' => 'MAIN-'.$sequence, 'name' => 'Main', 'disposition' => 'sellable']);
            $customer = OperationalCustomer::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'normalized_phone' => $member->canonical_phone, 'phone' => $member->canonical_phone]);
            LoyaltyCustomerLink::query()->create(['merchant_id' => $merchant->id, 'loyalty_member_id' => $member->id, 'operational_customer_id' => $customer->id]);
            $cart = Cart::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'register_id' => $register->id, 'inventory_location_id' => $location->id, 'customer_id' => $customer->id, 'created_by_user_id' => $user->id, 'last_edited_by_user_id' => $user->id, 'reference' => 'CART-'.$sequence, 'status' => CartStatus::Active, 'version' => 1, 'manual_cart_discount' => '0.00']);
            $carts[] = ['merchant' => $merchant->public_id, 'store' => $store->public_id, 'cart' => $cart->public_id, 'user' => $user->id, 'reward' => $reward->public_id, 'key' => (string) Str::uuid()];
        }

        $tasks = array_map(static function (array $identity): \Closure {
            return static function () use ($identity): string {
                $merchant = (new Merchant)->resolveRouteBinding($identity['merchant']);
                $store = (new Store)->resolveRouteBinding($identity['store']);
                $cart = (new Cart)->resolveRouteBinding($identity['cart']);
                $user = User::query()->findOrFail($identity['user']);
                if (! $merchant instanceof Merchant || ! $store instanceof Store || ! $cart instanceof Cart) {
                    throw new \RuntimeException('Loyalty concurrency fixture could not be resolved.');
                }
                try {
                    app(LoyaltyService::class)->reserve($merchant, $store, $cart, $user, $identity['key'], $identity['reward'], 1);

                    return 'reserved';
                } catch (HttpExceptionInterface $exception) {
                    return $exception->getStatusCode() === 409 ? 'conflict' : 'unexpected-'.$exception->getStatusCode();
                }
            };
        }, $carts);

        $results = Concurrency::run($tasks, timeout: 60);
        sort($results);
        $this->assertSame(['conflict', 'reserved'], $results);
        $this->assertDatabaseCount('loyalty_redemption_reservations', 1);
    }
}
