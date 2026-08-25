<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanySubscription
{
    public function handle(
        Request $request,
        Closure $next
    ): Response|RedirectResponse {

        $user = $request->user();

        /*
         * صفحات عمومی مثل Login
         */
        if ($user === null) {
            return $next($request);
        }

        /*
         * مالک کل سامانه محدود به اشتراک شرکت نیست.
         */
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->forceLogout(
                $request,
                'حساب کاربری شما به هیچ شرکتی متصل نیست.'
            );
        }

        /*
         * فقط active و demo امکان استفاده دارند.
         */
        if (!in_array(
            $company->status,
            ['active', 'demo'],
            true
        )) {
            $message = match ($company->status) {
                'suspended' =>
                    'اشتراک شرکت شما توسط مدیر سامانه تعلیق شده است.',

                'expired' =>
                    'اشتراک شرکت شما منقضی شده است.',

                default =>
                    'اشتراک شرکت شما فعال نیست.',
            };

            return $this->forceLogout(
                $request,
                $message
            );
        }

        /*
         * اشتراک هنوز شروع نشده.
         */
        if (
            $company->license_start !== null
            && today()->lt(
                $company->license_start
                    ->copy()
                    ->startOfDay()
            )
        ) {
            return $this->forceLogout(
                $request,
                'تاریخ شروع اشتراک شرکت هنوز فرا نرسیده است.'
            );
        }

        /*
         * روز license_end هنوز معتبر است.
         * از روز بعد دسترسی بسته می‌شود.
         */
        if (
            $company->license_end !== null
            && today()->gt(
                $company->license_end
                    ->copy()
                    ->startOfDay()
            )
        ) {
            return $this->forceLogout(
                $request,
                $company->status === 'demo'
                    ? 'دوره آزمایشی شرکت به پایان رسیده است.'
                    : 'اشتراک شرکت به پایان رسیده است. برای تمدید با مدیر سامانه تماس بگیرید.'
            );
        }

        return $next($request);
    }

    private function forceLogout(
        Request $request,
        string $message
    ): RedirectResponse {

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->route('login')
            ->withErrors([
                'username' => $message,
            ]);
    }
}