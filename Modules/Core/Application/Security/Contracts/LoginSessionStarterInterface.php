<?php

declare(strict_types=1);

namespace Modules\Core\Application\Security\Contracts;

use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\UserId;

interface LoginSessionStarterInterface
{
    public function start(
        UserId $userId,
        string $ipAddress,
        string $userAgent,
        string $computerName
    ): LoginSession;
}