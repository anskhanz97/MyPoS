<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Customers\CustomerScope;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkCustomerLoyaltyRequest;
use App\Http\Resources\CustomerResource;
use App\Models\LoyaltyCustomerLink;
use App\Models\LoyaltyMember;
use App\Models\Merchant;
use App\Models\OperationalCustomer;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerLoyaltyLinkController extends Controller
{
    public function __invoke(LinkCustomerLoyaltyRequest $request, Merchant $merchant, Store $store, OperationalCustomer $customer, RoleAuthorizer $authorizer, CustomerScope $scope): CustomerResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $authorizer->authorize($user, $merchant, Permission::LoyaltyManage, $store);
        abort_if($membership->role === MembershipRole::Cashier, 403, 'Cashiers cannot link loyalty identities.');
        abort_unless($customer->merchant_id === $merchant->id && $customer->store_id === $scope->storeId($merchant, $store), 404);
        $data = $request->validated();

        $customer = DB::transaction(function () use ($user, $merchant, $store, $customer, $data): OperationalCustomer {
            $locked = OperationalCustomer::query()->lockForUpdate()->findOrFail($customer->id);
            abort_unless($locked->version === (int) $data['expected_version'], 409, 'The customer changed. Reload before saving.');
            $member = null;
            if ($data['loyalty_member_id'] !== null) {
                $resolved = (new LoyaltyMember)->resolveRouteBinding((string) $data['loyalty_member_id']);
                abort_unless($resolved instanceof LoyaltyMember && $resolved->merchant_id === $merchant->id, 422, 'The loyalty member is outside this merchant.');
                abort_unless($locked->normalized_phone !== null && $resolved->canonical_phone === $locked->normalized_phone, 422, 'The customer and loyalty member must have the same normalized phone.');
                $member = $resolved;
                LoyaltyCustomerLink::query()->updateOrCreate(
                    ['operational_customer_id' => $locked->id],
                    ['merchant_id' => $merchant->id, 'loyalty_member_id' => $member->id],
                );
            } else {
                LoyaltyCustomerLink::query()->where('operational_customer_id', $locked->id)->delete();
            }
            $locked->increment('version');
            SecurityAuditEvent::query()->create([
                'merchant_id' => $merchant->id,
                'store_id' => $store->id,
                'actor_user_id' => $user->id,
                'event_code' => $member === null ? 'customer.loyalty_unlinked' : 'customer.loyalty_linked',
                'outcome' => 'succeeded',
                'metadata' => ['customer_id' => $locked->public_id, 'loyalty_member_id' => $member?->public_id, 'version' => $locked->version],
                'occurred_at' => now(),
            ]);

            return $locked->refresh()->load(['customerGroup', 'loyaltyLink.loyaltyMember', 'store']);
        });

        return new CustomerResource($customer);
    }
}
