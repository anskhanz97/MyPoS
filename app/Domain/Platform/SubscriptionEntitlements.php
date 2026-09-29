<?php

namespace App\Domain\Platform;

use App\Domain\Platform\Enums\EntitlementCapability;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Models\MerchantSubscription;

final class SubscriptionEntitlements
{
    public function allows(MerchantSubscription $subscription, EntitlementCapability $capability): bool
    {
        if (in_array($capability, [EntitlementCapability::PendingOfflineSubmission, EntitlementCapability::DataExport, EntitlementCapability::Support], true)) {
            return true;
        }

        return in_array($subscription->status, [SubscriptionStatus::Trial, SubscriptionStatus::Active, SubscriptionStatus::Grace], true);
    }
}
