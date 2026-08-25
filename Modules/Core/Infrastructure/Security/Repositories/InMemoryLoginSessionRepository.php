<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Security\Repositories;

use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;

final class InMemoryLoginSessionRepository implements LoginSessionRepositoryInterface
{
    /**
     * @var array<string, LoginSession>
     */
    private array $sessions = [];

    public function save(LoginSession $session): void
    {
        $this->sessions[$session->sessionId()->value()] = $session;
    }

    public function findById(SessionId $sessionId): ?LoginSession
    {
        return $this->sessions[$sessionId->value()] ?? null;
    }

    public function findActiveByUserId(UserId $userId): ?LoginSession
    {
        foreach ($this->sessions as $session) {
            if (
                $session->userId()->equals($userId)
                && $session->isActive()
            ) {
                return $session;
            }
        }

        return null;
    }

    public function delete(SessionId $sessionId): void
    {
        unset($this->sessions[$sessionId->value()]);
    }
}