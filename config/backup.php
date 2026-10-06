<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Production backup guard
    |--------------------------------------------------------------------------
    |
    | The public Render demo has an ephemeral filesystem and must never create
    | a pretend "backup" on its local disk.  Enable this only after an
    | off-platform, S3-compatible disk has been configured.
    |
    */
    'enabled' => env('BACKUP_ENABLED', false),
    'disk' => env('BACKUP_DISK', 'local'),
    'path' => env('BACKUP_PATH', 'backups/database'),
    'files_enabled' => env('BACKUP_FILES_ENABLED', true),
    'files_path' => env('BACKUP_FILES_PATH', 'backups/files'),
    'file_sources' => [
        'public' => storage_path('app/public'),
        'private' => storage_path('app/private'),
    ],
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'pg_dump_command' => ['pg_dump'],
];
