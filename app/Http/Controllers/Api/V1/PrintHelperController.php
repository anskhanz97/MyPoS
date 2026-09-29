<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Devices\DeviceCredentialResolver;
use App\Domain\Printing\ReportPrintAttempt;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportPrintAttemptRequest;
use App\Models\DeviceEnrollment;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;

class PrintHelperController extends Controller
{
    public function attempt(ReportPrintAttemptRequest $request, PrintJob $printJob, DeviceCredentialResolver $credentials, ReportPrintAttempt $reporter): JsonResponse
    {
        $device = (new DeviceEnrollment)->resolveRouteBinding((string) $request->validated('device_id'));
        abort_unless($device instanceof DeviceEnrollment, 401);
        $credentials->resolve($device, $request->header('X-Device-Key'), true);
        abort_unless($device->merchant_id === $printJob->merchant_id && $device->store_id === $printJob->store_id && $device->register_id === $printJob->register_id, 404);
        $data = $request->validated();
        unset($data['device_id']);
        $result = $reporter->handle($printJob, $data);

        return response()->json(['data' => ['print_job_id' => $result['job']->public_id, 'state' => $result['job']->state, 'attempt_id' => $result['attempt']->public_id, 'outcome' => $result['attempt']->outcome], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }
}
