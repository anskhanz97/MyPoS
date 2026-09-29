<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Devices\DeviceCredentialResolver;
use App\Domain\Offline\BuildOfflineBootstrap;
use App\Domain\Offline\IngestOfflineCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\OfflineBootstrapRequest;
use App\Http\Requests\OfflineTransactionsRequest;
use App\Models\DeviceEnrollment;
use Illuminate\Http\JsonResponse;

class OfflineController extends Controller
{
    public function bootstrap(OfflineBootstrapRequest $request, DeviceCredentialResolver $credentials, BuildOfflineBootstrap $builder): JsonResponse
    {
        $device = $this->device((string) $request->validated('device_id'));
        $credentials->resolve($device, $request->header('X-Device-Key'));
        abort_unless($device->register->public_id === $request->validated('register_id'), 404);
        $versions = $request->validated('supported_schema_versions');
        $versionList = is_array($versions) ? array_values(array_filter($versions, is_int(...))) : [];

        return response()->json(['data' => $builder->handle($device, $versionList)]);
    }

    public function transactions(OfflineTransactionsRequest $request, DeviceCredentialResolver $credentials, IngestOfflineCommands $ingest): JsonResponse
    {
        $device = $this->device((string) $request->validated('device_id'));
        $credentials->resolve($device, $request->header('X-Device-Key'), true);
        abort_unless($device->register->public_id === $request->validated('register_id'), 404);
        $commands = $request->validated('commands');

        $commandList = is_array($commands) ? array_values(array_filter($commands, is_array(...))) : [];

        return response()->json(['data' => ['results' => $ingest->handle($device, $commandList)]]);
    }

    private function device(string $id): DeviceEnrollment
    {
        $device = (new DeviceEnrollment)->resolveRouteBinding($id);
        abort_unless($device instanceof DeviceEnrollment, 404);
        $device->loadMissing(['merchant', 'store', 'register']);

        return $device;
    }
}
