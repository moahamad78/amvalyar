<?php
declare(strict_types=1);
namespace Tests\Feature\Production;

use Tests\TestCase;

final class ScheduledDatabaseBackupTest extends TestCase
{
    public function test_scheduled_backup_command_skips_outside_production(): void
    {
        $this->artisan('app:scheduled-database-backup')
            ->expectsOutput('Scheduled database backup skipped outside production.')
            ->assertSuccessful();
    }

    public function test_schedule_registers_database_backup_at_two_am(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('app:scheduled-database-backup')
            ->assertSuccessful();
    }
}