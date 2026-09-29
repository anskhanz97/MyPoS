<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\User;
use Illuminate\Http\Request;

class OwnerSubscriptionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Merchant $merchant, TenantAccess $access): SubscriptionResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $access->ownerOrForbidden($user, $merchant);
        $subscription = $merchant->subscription()->with(['merchant', 'plan.entitlements'])->first();
        abort_unless($subscription instanceof MerchantSubscription, 404);

        return new SubscriptionResource($subscription);
    }
}
