<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configured = (string) config('operations.health_key', '');
        if ($configured === '' || ! hash_equals($configured, (string) $request->header('X-Health-Key'))) {
            abort(404);
        }

        $checks = [];
        $checks['database'] = $this->check(fn () => DB::select('select 1'));
        $checks['cache'] = $this->check(function (): void {
            Cache::put('ops:readiness', now()->timestamp, 30);
            Cache::get('ops:readiness');
        });
        $checks['private_storage'] = $this->check(function (): void {
            $path = 'health/readiness.txt';
            Storage::disk((string) config('operations.private_disk', 'local'))->put($path, now()->toAtomString());
            Storage::disk((string) config('operations.private_disk', 'local'))->delete($path);
        });

        $checks['scheduler'] = $this->schedulerStatus(Cache::get('ops:scheduler-heartbeat'));
        $ready = ! in_array('failed', $checks, true) && $checks['scheduler'] === 'ok';

        return response()->json(['data' => ['service' => 'mypos', 'status' => $ready ? 'ready' : 'not_ready', 'checks' => $checks, 'request_id' => $request->attributes->get('request_id')]], $ready ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }

    private function check(callable $callback): string
    {
        try {
            $callback();

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function schedulerStatus(mixed $heartbeat): string
    {
        if (! is_int($heartbeat)) {
            return 'stale';
        }

        return time() - $heartbeat <= (int) config('operations.scheduler_max_age_seconds', 180) ? 'ok' : 'stale';
    }
}
