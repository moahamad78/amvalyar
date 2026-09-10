<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Application\Security\Contracts\AuthenticationServiceInterface;
use Modules\Core\Application\Security\Contracts\LoginSessionStarterInterface;
use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Application\Security\Services\AuthenticationService;
use Modules\Core\Application\Security\Services\LoginSessionService;
use Modules\Core\Infrastructure\Security\Repositories\DatabaseLoginSessionRepository;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            LoginSessionRepositoryInterface::class,
            DatabaseLoginSessionRepository::class
        );

        $this->app->bind(
            LoginSessionStarterInterface::class,
            LoginSessionService::class
        );

        $this->app->bind(
            AuthenticationServiceInterface::class,
            AuthenticationService::class
        );
    }

    public function boot(): void
    {
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        if ($this->app->environment('production')) {
            if (
                (string) config('database.default')
                ===
                'sqlite'
            ) {
                throw new \RuntimeException(
                    'Production must not run on SQLite. Configure PostgreSQL or MySQL explicitly.'
                );
            }

            if ((bool) config('app.debug')) {
                throw new \RuntimeException(
                    'APP_DEBUG must be false in production.'
                );
            }
        }
    }
}
