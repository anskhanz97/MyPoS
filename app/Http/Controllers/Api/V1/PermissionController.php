<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\FixedRolePermissions;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Merchant $merchant, TenantAccess $access, FixedRolePermissions $permissions): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $access->membershipOrNotFound($user, $merchant);

        return response()->json(['data' => [
            'role' => $membership->role->value,
            'permissions' => array_map(fn ($permission): string => $permission->value, $permissions->forRole($membership->role)),
        ]]);
    }
}
