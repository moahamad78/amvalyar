<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\ValueObjects\SessionId;

final class EnsureActiveLoginSession
{
    public function __construct(
        private LoginSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response|RedirectResponse {
        $sessionId = $request->session()->get(
            'domain_session_id'
        );

        if (
            !is_string($sessionId)
            || trim($sessionId) === ''
        ) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'username' => 'لطفاً ابتدا وارد حساب کاربری شوید.',
                ]);
        }

        try {
            $session = $this->sessionRepository->findById(
                SessionId::fromString($sessionId)
            );
        } catch (\Throwable) {
            $session = null;
        }

        if (
            $session === null
            || !$session->isActive()
        ) {
            $request->session()->forget([
                'domain_session_id',
                'domain_user_id',
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'username' => 'نشست شما معتبر نیست. لطفاً دوباره وارد شوید.',
                ]);
        }

        $session->touch();

        $this->sessionRepository->save($session);

        $request->attributes->set('active_login_session_verified', true);
        return $next($request);
    }
}
