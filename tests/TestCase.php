<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $database = (string) (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ''));
        $databaseName = basename(str_replace('\\', '/', $database));

        // Several legacy feature tests use RefreshDatabase. Refuse to boot them
        // unless the caller explicitly selected a disposable audit database.
        // This check runs before Laravel's testing traits, so an accidental
        // `php artisan test` can never migrate or truncate development data.
        if ($database !== ':memory:' && ! str_starts_with($databaseName, 'audit-')) {
            throw new RuntimeException(
                'Unsafe test database. Run tools/run-tests-isolated.ps1 instead of php artisan test.'
            );
        }

        parent::setUp();
    }
}
