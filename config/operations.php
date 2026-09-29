<?php

return [
    'health_key' => env('OPS_HEALTH_KEY'),
    'scheduler_max_age_seconds' => (int) env('OPS_SCHEDULER_MAX_AGE', 180),
    'private_disk' => env('OPS_PRIVATE_DISK', 'local'),
    'export_retention_days' => (int) env('OPS_EXPORT_RETENTION_DAYS', 7),
];
