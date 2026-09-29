<?php

namespace Tests\Feature;

use App\Domain\Loyalty\LoyaltyService;
use App\Domain\Loyalty\PublicLoyaltyService;
use App\Models\LoyaltyMember;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyPublicEnrollment;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicLoyaltyConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_parallel_join_commands_create_one_member_and_no_welcome_credit(): void
    {
        $merchant = Merchant::factory()->create();
        $owner = User::factory()->create();
        $program = app(LoyaltyService::class)->publish($merchant, $owner, 'Concurrent', 'concurrent-public', '0.01', null, []);
        $commands = [['slug' => $program->slug, 'key' => (string) Str::uuid()], ['slug' => $program->slug, 'key' => (string) Str::uuid()]];
        $tasks = array_map(static function (array $command): \Closure {
            return static function () use ($command): string {
                $program = LoyaltyProgram::query()->where('slug', $command['slug'])->firstOrFail();
                $result = app(PublicLoyaltyService::class)->join($program, $command['key'], ['phone' => '03001234567', 'display_name' => 'Ayesha Khan']);

                return $result['member']->public_id;
            };
        }, $commands);
        $ids = Concurrency::run($tasks, timeout: 60);
        $this->assertCount(1, array_unique($ids));
        $this->assertSame(1, LoyaltyMember::query()->where('merchant_id', $merchant->id)->count());
        $this->assertSame(2, LoyaltyPublicEnrollment::query()->count());
        $this->assertDatabaseCount('loyalty_point_entries', 0);
    }
}
