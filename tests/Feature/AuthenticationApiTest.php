<?php

namespace Tests\Feature;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_user_can_login_use_the_session_and_logout(): void
    {
        User::factory()->create(['email' => 'owner@example.test', 'password' => 'Strong-password-77']);

        $this->postJson('/api/v1/auth/login', ['email' => 'OWNER@example.test', 'password' => 'Strong-password-77'])
            ->assertOk()->assertJsonPath('data.email', 'owner@example.test')->assertJsonMissingPath('data.password');
        $this->getJson('/api/v1/bootstrap')->assertOk();
        $this->deleteJson('/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/bootstrap')->assertUnauthorized();
    }

    public function test_inactive_user_and_bad_password_receive_the_same_shape_without_secret_audit_data(): void
    {
        User::factory()->create(['email' => 'inactive@example.test', 'password' => 'Strong-password-77', 'is_active' => false]);

        foreach (['Strong-password-77', 'wrong-password'] as $password) {
            $this->postJson('/api/v1/auth/login', ['email' => 'inactive@example.test', 'password' => $password])
                ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonPath('field_errors.0.pointer', '/email');
        }

        $events = SecurityAuditEvent::query()->where('event_code', 'password_login')->where('outcome', 'failed')->get();
        $this->assertCount(2, $events);
        $this->assertStringNotContainsString('inactive@example.test', $events->toJson());
        $this->assertStringNotContainsString('Strong-password-77', $events->toJson());
    }

    public function test_forgot_password_is_non_enumerating_and_reset_changes_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.test']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertAccepted();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])->assertAccepted();
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::broker()->createToken($user);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'New-strong-password-88',
            'password_confirmation' => 'New-strong-password-88',
        ])->assertOk();

        $this->assertTrue(Hash::check('New-strong-password-88', $user->refresh()->password));
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        User::factory()->create(['email' => 'limited@example.test']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'limited@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'limited@example.test', 'password' => 'wrong'])
            ->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
    }
}
