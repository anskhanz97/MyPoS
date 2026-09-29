<?php

namespace Tests\Unit;

use App\Domain\Platform\Enums\EntitlementCapability;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\SubscriptionEntitlements;
use App\Models\MerchantSubscription;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SubscriptionEntitlementTest extends TestCase
{
    /** @return array<string, array{SubscriptionStatus, bool}> */
    public static function operationStates(): array
    {
        return ['trial' => [SubscriptionStatus::Trial, true], 'active' => [SubscriptionStatus::Active, true], 'grace' => [SubscriptionStatus::Grace, true], 'restricted' => [SubscriptionStatus::Restricted, false], 'cancelled' => [SubscriptionStatus::Cancelled, false]];
    }

    #[DataProvider('operationStates')]
    public function test_new_operations_follow_subscription_state(SubscriptionStatus $status, bool $expected): void
    {
        $subscription = new MerchantSubscription(['status' => $status]);
        $this->assertSame($expected, (new SubscriptionEntitlements)->allows($subscription, EntitlementCapability::NewOperations));
    }

    public function test_recovery_export_and_support_survive_every_billing_state(): void
    {
        $service = new SubscriptionEntitlements;
        foreach (SubscriptionStatus::cases() as $status) {
            $subscription = new MerchantSubscription(['status' => $status]);
            $this->assertTrue($service->allows($subscription, EntitlementCapability::PendingOfflineSubmission));
            $this->assertTrue($service->allows($subscription, EntitlementCapability::DataExport));
            $this->assertTrue($service->allows($subscription, EntitlementCapability::Support));
        }
    }
}
