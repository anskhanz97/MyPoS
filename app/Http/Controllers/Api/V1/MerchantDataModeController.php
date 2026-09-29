<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Exceptions\DataModeLocked;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMerchantDataModeRequest;
use App\Models\Merchant;
use App\Models\User;

class MerchantDataModeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateMerchantDataModeRequest $request, Merchant $merchant, TenantAccess $access): never
    {
        $user = $request->user();
        assert($user instanceof User);
        $access->ownerOrForbidden($user, $merchant);

        throw new DataModeLocked;
    }
}
