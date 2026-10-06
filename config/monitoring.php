<?php

declare(strict_types=1);

return [
    'enabled' => env('MONITORING_ENABLED', false),
    'storage_disk' => env('MONITORING_STORAGE_DISK', 'local'),
    'heartbeat_url' => env('MONITORING_HEARTBEAT_URL'),
];
