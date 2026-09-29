<?php

namespace Tests\Feature;

use App\Domain\Cash\OpenCashSession;
use App\Models\CashSession;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class CashSessionConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_parallel_open_attempts_create_only_one_active_session(): void
    {
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $register = Register::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id]);
        $actor = User::factory()->create();
        $ids = ['merchant' => $merchant->public_id, 'store' => $store->public_id, 'register' => $register->public_id, 'actor' => $actor->id];
        $tasks = [];
        foreach ([1, 2] as $sequence) {
            $tasks[] = static function () use ($ids, $sequence): string {
                $resolvedMerchant = (new Merchant)->resolveRouteBinding($ids['merchant']);
                $resolvedStore = (new Store)->resolveRouteBinding($ids['store']);
                $resolvedRegister = (new Register)->resolveRouteBinding($ids['register']);
                $resolvedActor = User::query()->find($ids['actor']);
                if (! $resolvedMerchant instanceof Merchant || ! $resolvedStore instanceof Store || ! $resolvedRegister instanceof Register || ! $resolvedActor instanceof User) {
                    throw new \RuntimeException('Concurrent cash-session fixture could not be resolved.');
                }
                try {
                    app(OpenCashSession::class)->handle($resolvedMerchant, $resolvedStore, $resolvedRegister, $resolvedActor, '00000000-0000-7000-8000-00000000030'.$sequence, '100.00', now()->toIso8601String());

                    return 'opened';
                } catch (ConflictHttpException) {
                    return 'conflict';
                }
            };
        }

        $results = Concurrency::run($tasks, timeout: 30);
        sort($results);
        $this->assertSame(['conflict', 'opened'], $results);
        $this->assertSame(1, CashSession::query()->whereNotNull('active_guard')->count());
        $this->assertDatabaseCount('cash_movements', 1);
    }
}
