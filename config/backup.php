<?php

declare(strict_types=1);

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'path' => env('BACKUP_PATH', 'backups/database'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
];