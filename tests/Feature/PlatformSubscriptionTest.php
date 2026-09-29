<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\MerchantSubscription;
use App\Models\Plan;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformOperator;
use App\Models\SubscriptionLifecycleEvent;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_endpoints_reject_an_owner_without_platform_authority(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->create();
        MerchantMembership::factory()->owner()->for($merchant)->for($owner)->create();
        $this->actingAs($owner)->getJson('/api/v1/platform/plans')->assertForbidden();
        $this->actingAs($owner)->postJson("/api/v1/platform/merchants/{$merchant->public_id}/subscription", [])->assertForbidden();
    }

    public function test_operator_creates_plan_and_assigns_one_subscription_only(): void
    {
        [$user] = $this->operatorFixture();
        $merchant = Merchant::factory()->create();
        $plan = $this->actingAs($user)->postJson('/api/v1/platform/plans', [
            'code' => 'complete', 'name' => 'Complete', 'billing_period_days' => 30, 'grace_days' => 7, 'price' => '5000.00',
            'entitlements' => [['code' => 'all_modules', 'enabled' => true, 'limit_value' => null]],
        ])->assertCreated()->assertJsonPath('data.price', '5000.00')->json('data');
        $payload = ['plan_id' => $plan['id'], 'status' => 'trial', 'starts_at' => '2026-09-29T00:00:00+05:00', 'trial_ends_at' => '2026-10-13T00:00:00+05:00'];
        $this->actingAs($user)->postJson("/api/v1/platform/merchants/{$merchant->public_id}/subscription", $payload)->assertCreated()->assertJsonPath('data.status', 'trial')->assertJsonPath('data.capabilities.new_operations', true);
        $this->actingAs($user)->postJson("/api/v1/platform/merchants/{$merchant->public_id}/subscription", $payload)->assertConflict();
        $this->assertSame(1, MerchantSubscription::query()->count());
        $this->assertSame(1, SubscriptionLifecycleEvent::query()->count());
        $this->assertDatabaseHas('platform_audit_events', ['event_code' => 'subscription.assigned']);
    }

    public function test_operator_provisions_merchant_owner_store_and_subscription_without_becoming_owner(): void
    {
        Notification::fake();
        [$user] = $this->operatorFixture();
        $plan = Plan::query()->create(['code' => 'launch', 'name' => 'Launch', 'billing_period_days' => 30, 'grace_days' => 7, 'price' => '3000.00', 'currency' => 'PKR']);
        $this->actingAs($user)->postJson('/api/v1/platform/merchants', [
            'name' => 'Provisioned Retail', 'slug' => 'provisioned-retail', 'store_data_mode' => 'shared', 'timezone' => 'Asia/Karachi',
            'store_name' => 'Main Store', 'store_code' => 'MAIN', 'owner_name' => 'Merchant Owner', 'owner_email' => 'new-owner@example.test',
            'plan_id' => $plan->public_id, 'subscription_status' => 'trial', 'starts_at' => now()->toAtomString(),
            'trial_ends_at' => now()->addDays(14)->toAtomString(), 'review_confirmed' => true,
        ])->assertCreated()->assertJsonPath('data.status', 'trial');
        $owner = User::query()->where('email', 'new-owner@example.test')->sole();
        $this->assertSame(MembershipRole::Owner, $owner->merchantMemberships()->sole()->role);
        $this->assertSame(0, $user->merchantMemberships()->count());
        Notification::assertSentTo($owner, ResetPassword::class);
    }

    public function test_manual_payment_is_idempotent_and_separate_from_pos_security_events(): void
    {
        [$user, $operator] = $this->operatorFixture();
        $subscription = $this->subscriptionFixture();
        $payload = ['idempotency_key' => (string) Str::uuid7(), 'reference' => 'BANK-0091', 'amount' => '5000.00', 'paid_on' => '2026-09-29', 'periods' => 1, 'note' => 'Manual bank receipt checked.'];
        $first = $this->actingAs($user)->postJson("/api/v1/platform/subscriptions/{$subscription->public_id}/payments", $payload)->assertCreated();
        $second = $this->actingAs($user)->postJson("/api/v1/platform/subscriptions/{$subscription->public_id}/payments", $payload)->assertCreated();
        $this->assertSame($first->json('data.payment_id'), $second->json('data.payment_id'));
        $this->assertSame(1, SubscriptionPayment::query()->count());
        $this->assertSame($operator->id, SubscriptionPayment::query()->sole()->recorded_by_platform_operator_id);
        $this->assertDatabaseMissing('security_audit_events', ['event_code' => 'subscription.payment_recorded']);
        $this->assertDatabaseHas('platform_audit_events', ['event_code' => 'subscription.payment_recorded']);

        $changedPayload = $payload;
        $changedPayload['amount'] = '6000.00';
        $this->actingAs($user)->postJson("/api/v1/platform/subscriptions/{$subscription->public_id}/payments", $changedPayload)->assertConflict();
        $duplicateReference = $payload;
        $duplicateReference['idempotency_key'] = (string) Str::uuid7();
        $this->actingAs($user)->postJson("/api/v1/platform/subscriptions/{$subscription->public_id}/payments", $duplicateReference)->assertConflict();
    }

    public function test_restriction_preserves_recovery_capabilities_and_is_audited(): void
    {
        [$user] = $this->operatorFixture();
        $subscription = $this->subscriptionFixture();
        $this->actingAs($user)->patchJson("/api/v1/platform/subscriptions/{$subscription->public_id}", ['status' => 'restricted', 'reason' => 'Renewal overdue after manual review.'])
            ->assertOk()->assertJsonPath('data.status', 'restricted')->assertJsonPath('data.capabilities.new_operations', false)
            ->assertJsonPath('data.capabilities.pending_offline_submission', true)->assertJsonPath('data.capabilities.data_export', true);
        $this->assertSame('restricted', $subscription->merchant->refresh()->status->value);
        $this->assertDatabaseHas('platform_audit_events', ['event_code' => 'subscription.state_changed', 'reason' => 'Renewal overdue after manual review.']);
        $this->actingAs($user)->patchJson("/api/v1/platform/subscriptions/{$subscription->public_id}", ['status' => 'grace', 'reason' => 'Invalid backwards transition.'])->assertConflict();
    }

    public function test_owner_sees_read_only_status_but_manager_does_not(): void
    {
        $subscription = $this->subscriptionFixture();
        $owner = User::factory()->create();
        MerchantMembership::factory()->owner()->for($subscription->merchant)->for($owner)->create();
        $manager = User::factory()->create();
        MerchantMembership::factory()->for($subscription->merchant)->for($manager)->create(['role' => MembershipRole::Manager]);
        $this->actingAs($owner)->getJson("/api/v1/merchants/{$subscription->merchant->public_id}/subscription")->assertOk()->assertJsonPath('data.plan.name', 'Complete');
        $this->actingAs($manager)->getJson("/api/v1/merchants/{$subscription->merchant->public_id}/subscription")->assertForbidden();
    }

    public function test_support_access_is_explicit_scoped_expiring_and_audited(): void
    {
        [$user] = $this->operatorFixture();
        $merchant = Merchant::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/platform/support-access-grants', [
            'merchant_id' => $merchant->public_id, 'scope' => 'diagnostics',
            'purpose' => 'Investigate merchant reported register bootstrap error.', 'expires_at' => now()->addHour()->toAtomString(),
        ])->assertCreated()->assertJsonPath('data.scope', 'diagnostics');
        $this->assertSame(1, PlatformAuditEvent::query()->where('event_code', 'support.access_granted')->count());
        $this->actingAs($user)->getJson("/api/v1/platform/merchants/{$merchant->public_id}/diagnostics")
            ->assertOk()->assertJsonPath('data.merchant.id', $merchant->public_id)->assertJsonMissingPath('data.merchant.slug');
        $this->assertSame(1, PlatformAuditEvent::query()->where('event_code', 'support.diagnostics_viewed')->count());
    }

    /** @return array{User, PlatformOperator} */
    private function operatorFixture(): array
    {
        $user = User::factory()->create();

        return [$user, PlatformOperator::factory()->for($user)->create()];
    }

    private function subscriptionFixture(): MerchantSubscription
    {
        $merchant = Merchant::factory()->create();
        $plan = Plan::query()->create(['code' => 'complete-'.Str::lower(Str::random(5)), 'name' => 'Complete', 'billing_period_days' => 30, 'grace_days' => 7, 'price' => '5000.00', 'currency' => 'PKR']);

        return MerchantSubscription::query()->create(['merchant_id' => $merchant->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'current_period_ends_at' => now()->addDays(30)]);
    }
}
