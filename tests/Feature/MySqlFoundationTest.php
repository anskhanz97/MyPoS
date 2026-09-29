<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlFoundationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_disposable_database_runs_migrations_and_persists_synthetic_factory_data(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());

        $user = User::factory()->create([
            'name' => 'Foundation Test Staff',
            'email' => 'foundation.staff@example.test',
        ]);

        $this->assertModelExists($user);
        $this->assertSame('foundation.staff@example.test', $user->email);
    }
}
