<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PermissionRegistryService;
use Illuminate\Console\Command;

final class SyncPermissions extends Command
{
    protected $signature =
        'permissions:sync';

    protected $description =
        'Sync central permission registry with the database';


    public function handle(
        PermissionRegistryService $registry
    ): int {
        $result =
            $registry->sync();

        $this->info(
            'Permission registry synced.'
        );

        $this->line(
            'Registered: '
            . $result['registered_names']
        );

        $this->line(
            'Created: '
            . $result['created_count']
        );

        $this->line(
            'Updated: '
            . $result['updated_count']
        );

        return self::SUCCESS;
    }
}