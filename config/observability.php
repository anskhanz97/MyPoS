<?php

return [
    'enabled' => env('DB_LOGGING_ENABLED', true),
    'build_version' => env('APP_BUILD_VERSION', is_file(base_path('bootstrap/cache/log-build.php')) ? require base_path('bootstrap/cache/log-build.php') : 'development-unstamped'),
    'retention_days' => ['application_logs' => 30, 'api_request_logs' => 14, 'integration_logs' => 90],
    'worker_stale_seconds' => 180,
];
