<?php

namespace App\Http\Middleware;

use App\Services\RoleAccessService;
use Closure;
use Illuminate\Http\Request;

final class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }
        $route = (string) $request->route()?->getName();
        $access = app(RoleAccessService::class);
        if (str_starts_with($route, 'initial-setup.') || str_starts_with($route, 'bulk-import.')) {
            abort_unless($user->hasPermission('assets.create'), 403);
        }
        if (str_starts_with($route, 'organizational-assets.')) {
            abort_unless($user->hasPermission('assets.view') && $access->scope($user) !== 'self', 403);
        }
        if (str_starts_with($route, 'asset-reference.')) {
            abort_unless($user->hasPermission('asset_types.manage'), 403);
        }
        if (str_starts_with($route, 'operational-alerts.')) {
            abort_unless($access->scope($user) !== 'self', 403);
        }
        if (str_starts_with($route, 'reports.repairs')) {
            abort_unless($access->financial($user), 403);
        }
        return $next($request);
    }
}
