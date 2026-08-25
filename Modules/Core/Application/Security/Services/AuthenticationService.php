<?php

declare(strict_types=1);

namespace Modules\Core\Application\Security\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Modules\Core\Application\Security\Contracts\AuthenticationServiceInterface;
use Modules\Core\Application\Security\Contracts\LoginSessionStarterInterface;
use Modules\Core\Domain\Security\Entities\LoginSession;

final class AuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private LoginSessionStarterInterface $loginSessionStarter
    ) {
    }

    public function authenticate(
        string $username,
        string $password,
        string $ipAddress,
        string $userAgent,
        string $computerName
    ): LoginSession {
        $username = trim($username);

        if ($username === '') {
            throw new InvalidArgumentException(
                'Username cannot be empty.'
            );
        }

        if ($password === '') {
            throw new InvalidArgumentException(
                'Password cannot be empty.'
            );
        }

        $user = User::query()
            ->where('username', $username)
            ->first();

        if ($user === null) {
            throw new InvalidArgumentException(
                'Invalid username or password.'
            );
        }

        if (!$user->isActive()) {
            throw new InvalidArgumentException(
                'User account is inactive.'
            );
        }

        if (!Hash::check($password, $user->password)) {
            throw new InvalidArgumentException(
                'Invalid username or password.'
            );
        }

        return $this->loginSessionStarter->startSession(
            userId: \Modules\Core\Domain\Security\ValueObjects\UserId::fromInt($user->id),
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            computerName: $computerName,
        );
    }
}