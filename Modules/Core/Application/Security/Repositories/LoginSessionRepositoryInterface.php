<?php

declare(strict_types=1);

namespace Modules\Core\Application\Security\Repositories;

use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;

interface LoginSessionRepositoryInterface
{
    public function save(LoginSession $session): void;

    public function findById(SessionId $sessionId): ?LoginSession;

    public function findActiveByUserId(UserId $userId): ?LoginSession;

    public function delete(SessionId $sessionId): void;
}