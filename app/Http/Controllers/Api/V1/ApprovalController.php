<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Actions\IssueApproval;
use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\StaffSessionResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApprovalRequest;
use App\Http\Resources\ApprovalResource;
use App\Models\DeviceEnrollment;
use App\Models\MerchantMembership;
use Illuminate\Http\JsonResponse;

class ApprovalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function store(StoreApprovalRequest $request, DeviceEnrollment $device, StaffSessionResolver $sessions, IssueApproval $issue): JsonResponse
    {
        $requester = $sessions->resolve($device, $request->header('X-Staff-Session'));
        $approver = (new MerchantMembership)->resolveRouteBinding($request->string('approver_membership_id')->toString());
        abort_unless($approver instanceof MerchantMembership, 404);
        $result = $issue->handle(
            $requester,
            $approver,
            Permission::from($request->string('permission')->toString()),
            $request->string('target_type')->toString(),
            $request->string('target_id')->toString(),
            $request->string('reason')->toString(),
            $request->string('approver_pin')->toString(),
        );

        return response()->json([
            'data' => (new ApprovalResource($result['approval']))->resolve(),
            'approval_token' => $result['token'],
        ], 201);
    }
}
