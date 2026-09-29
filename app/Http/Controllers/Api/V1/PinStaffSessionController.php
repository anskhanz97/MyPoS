<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Actions\SwitchDeviceStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\SwitchStaffRequest;
use App\Models\DeviceEnrollment;
use App\Models\MerchantMembership;
use Illuminate\Http\JsonResponse;

class PinStaffSessionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SwitchStaffRequest $request, DeviceEnrollment $device, SwitchDeviceStaff $switch): JsonResponse
    {
        $membership = (new MerchantMembership)->resolveRouteBinding($request->string('membership_id')->toString());
        abort_unless($membership instanceof MerchantMembership, 404);

        $result = $switch->handle(
            $device,
            $membership,
            (string) $request->header('X-Device-Key'),
            $request->string('pin')->toString(),
        );

        return response()->json(['data' => [
            'session_id' => $result['session']->public_id,
            'staff_session_token' => $result['token'],
            'expires_at' => $result['session']->expires_at->toAtomString(),
        ]], 201);
    }
}
