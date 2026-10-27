<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CompanyFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanyFeatureAccess
{
    public function __construct(
        private readonly CompanyFeatureService $features
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin()) {
            return $next($request);
        }

        $feature = $this->features->featureForRoute(
            $request->route()?->getName()
        );

        if ($feature !== null && ! $this->features->enabledForUser($user, $feature)) {
            abort(403, 'این قابلیت در پلن شرکت شما فعال نیست. برای فعال‌سازی با مدیر سامانه تماس بگیرید.');
        }

        return $next($request);
    }
}
