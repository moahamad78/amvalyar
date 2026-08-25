<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Security\Policies;

use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\UserId;

final class ActiveSessionPolicy
{
    /**
     * @param LoginSession[] $sessions
     */
    public function revokeOtherActiveSessions(
        UserId $userId,
        LoginSession $newSession,
        array $sessions,
    ): void {
        foreach ($sessions as $session) {
            if (!$session instanceof LoginSession) {
                continue;
            }

            if (
                $session->userId()->equals($userId)
                && $session->sessionId()->value() !== $newSession->sessionId()->value()
                && $session->isActive()
            ) {
                $session->revoke();
            }
        }
    }
}