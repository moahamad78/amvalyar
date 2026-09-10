<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureActiveLoginSession;
use App\Http\Middleware\EnsureCompanySubscription;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\WorkspaceRequestSupport;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->append(AddSecurityHeaders::class);

        $middleware->appendToGroup(
            'web',
            EnsureCompanySubscription::class
        );
        $middleware->appendToGroup('web', WorkspaceRequestSupport::class);
        $middleware->alias([
            'active.session' => EnsureActiveLoginSession::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
