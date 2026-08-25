<?php

declare(strict_types=1);

namespace Modules\Core\Application\Security\Contracts;

use App\Models\User;
use Modules\Core\Domain\Security\Entities\LoginSession;

interface AuthenticationServiceInterface
{
    public function authenticate(
        string $username,
        string $password,
        string $ipAddress,
        string $userAgent,
        string $computerName
    ): LoginSession;
}