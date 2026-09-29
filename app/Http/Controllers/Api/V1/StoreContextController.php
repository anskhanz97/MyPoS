<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\TenantAccess;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\SwitchStoreContextRequest;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StoreContextController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        SwitchStoreContextRequest $request,
        Merchant $merchant,
        Store $store,
        TenantAccess $access,
    ): JsonResponse {
        $user = $request->user();
        assert($user instanceof User);
        $access->assignedStoreOrNotFound($user, $merchant, $store);

        $context = new TenantContext($merchant->public_id, $store->public_id);

        return response()->json(['data' => $context]);
    }
}
