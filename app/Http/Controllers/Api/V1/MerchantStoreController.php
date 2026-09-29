<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Actions\CreateStore;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantStoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Merchant $merchant, TenantAccess $access): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);
        $membership = $access->membershipOrNotFound($user, $merchant);

        $stores = Store::query()
            ->whereBelongsTo($merchant)
            ->when(
                $membership->role !== MembershipRole::Owner,
                fn ($query) => $query->whereHas('assignments', fn ($assignments) => $assignments->whereBelongsTo($membership, 'membership')),
            )
            ->orderBy('name')
            ->get();

        return StoreResource::collection($stores);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoreRequest $request, Merchant $merchant, TenantAccess $access, CreateStore $create): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $membership = $access->ownerOrForbidden($user, $merchant);

        $store = $create->handle($merchant, $membership, [
            'name' => $request->string('name')->toString(),
            'code' => $request->string('code')->toString(),
            'timezone' => $request->string('timezone')->toString(),
        ]);

        return (new StoreResource($store))
            ->response()
            ->setStatusCode(201);
    }
}
