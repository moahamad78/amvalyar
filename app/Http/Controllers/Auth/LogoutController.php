<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\ValueObjects\SessionId;

final class LogoutController extends Controller
{
    public function __construct(
        private LoginSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function logout(Request $request): RedirectResponse
    {
        $sessionId = $request->session()->get(
            'domain_session_id'
        );

        if (
            is_string($sessionId)
            && trim($sessionId) !== ''
        ) {
            try {
                $session = $this->sessionRepository->findById(
                    SessionId::fromString($sessionId)
                );

                if ($session !== null && $session->isActive()) {
                    $session->revoke();

                    $this->sessionRepository->save($session);
                }
            } catch (\Throwable) {
                // Logout باید حتی در صورت خطای Session هم انجام شود.
            }
        }

        $request->session()->forget([
            'domain_session_id',
            'domain_user_id',
        ]);

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'با موفقیت از حساب کاربری خارج شدید.');
    }
}
