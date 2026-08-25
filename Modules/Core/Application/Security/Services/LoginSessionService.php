<?php

declare(strict_types=1);

namespace Modules\Core\Application\Security\Services;

use Modules\Core\Application\Security\Contracts\LoginSessionStarterInterface;
use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;

final class LoginSessionService implements LoginSessionStarterInterface
{
    public function __construct(
        private LoginSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function start(
        UserId $userId,
        string $ipAddress,
        string $userAgent,
        string $computerName
    ): LoginSession {
        return $this->startSession(
            $userId,
            $ipAddress,
            $userAgent,
            $computerName
        );
    }

    public function startSession(
        UserId $userId,
        string $ipAddress,
        string $userAgent,
        string $computerName
    ): LoginSession {
        $activeSession = $this->sessionRepository
            ->findActiveByUserId($userId);

        if ($activeSession !== null) {
            $activeSession->revoke();

            $this->sessionRepository->save($activeSession);
        }

        $newSession = LoginSession::start(
            SessionId::generate(),
            $userId,
            $ipAddress,
            $userAgent,
            $computerName
        );

        $this->sessionRepository->save($newSession);

        return $newSession;
    }
}