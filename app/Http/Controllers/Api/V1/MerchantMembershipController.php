<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Actions\CreateMembership;
use App\Domain\Auth\Actions\UpdateMembership;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMembershipRequest;
use App\Http\Requests\UpdateMembershipRequest;
use App\Http\Resources\MerchantMembershipResource;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantMembershipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Merchant $merchant, TenantAccess $access): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $access->ownerOrForbidden($user, $merchant);

        return MerchantMembershipResource::collection(
            $merchant->memberships()->with(['user', 'storeAssignments.store'])->orderBy('id')->get(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMembershipRequest $request, Merchant $merchant, TenantAccess $access, CreateMembership $create): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $access->ownerOrForbidden($user, $merchant);
        $storeIds = [];
        foreach ($request->array('store_ids') as $storeId) {
            $storeIds[] = (string) $storeId;
        }
        $membership = $create->handle($merchant, [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'role' => $request->string('role')->toString(),
            'pin' => $request->string('pin')->toString(),
            'store_ids' => $storeIds,
        ]);

        return (new MerchantMembershipResource($membership))->response()->setStatusCode(201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMembershipRequest $request, Merchant $merchant, MerchantMembership $membership, TenantAccess $access, UpdateMembership $update): MerchantMembershipResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $access->ownerOrForbidden($user, $merchant);

        return new MerchantMembershipResource($update->handle($merchant, $membership, $request->validated()));
    }
}
