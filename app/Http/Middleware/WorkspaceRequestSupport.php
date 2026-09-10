<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Support\JalaliDate;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class WorkspaceRequestSupport
{
    public function handle(Request $request, Closure $next)
    {
        foreach (['from', 'to', 'planned_date', 'expected_return_at'] as $name) {
            if ($request->has($name.'_jalali')) {
                try {
                    $value = $request->input($name.'_jalali');
                    if (! is_string($value) && $value !== null) {
                        throw new \InvalidArgumentException;
                    }
                    $request->merge([$name => JalaliDate::toGregorianDate($value)]);
                } catch (\Throwable) {
                    throw ValidationException::withMessages([$name.'_jalali' => 'تاریخ شمسی معتبر وارد کنید.']);
                }
            }
        }
        $actor = $request->user();
        $response = $next($request);
        // Never record request bodies, passwords or session tokens.
        $validationFailed = in_array('errors', $request->session()->get('_flash.new', []), true);
        $routeName = (string) ($request->route()?->getName() ?? 'unnamed');
        // These workflows already write their own domain audit events; a generic
        // request event would duplicate the history and break chronological views.
        $hasDomainAudit = str_starts_with($routeName, 'asset-repairs.')
            || str_starts_with($routeName, 'asset-movements.')
            || str_starts_with($routeName, 'inventory-requests.');
        if ($actor && ! $hasDomainAudit && $request->attributes->get('active_login_session_verified')
            && ! $request->isMethodSafe() && $response->getStatusCode() < 400 && ! $validationFailed) {
            $subject = collect($request->route()?->parameters() ?? [])->first(fn ($value) => $value instanceof Model);
            AuditLog::query()->create([
                'company_id' => $actor->isSuperAdmin() ? ($subject?->company_id ?? $actor->company_id) : $actor->company_id,
                'user_id' => $actor->id,
                'action' => 'request.'.$routeName,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject ? mb_substr((string) ($subject->name ?? $subject->title ?? $subject->code ?? ''), 0, 255) : null,
                'description' => 'درخواست '.$request->method().' پردازش شد (HTTP '.$response->getStatusCode().').',
                'ip_address' => $request->ip(), 'user_agent' => mb_substr($request->userAgent() ?? '', 0, 1000),
            ]);
        }

        return $response;
    }
}
