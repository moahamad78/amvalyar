<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Security\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Modules\Core\Domain\Entities\Entity;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;

final class LoginSession extends Entity
{
    private ?DateTimeImmutable $revokedAt = null;

    private function __construct(
        private readonly SessionId $sessionId,
        private readonly UserId $userId,
        private readonly string $ipAddress,
        private readonly string $userAgent,
        private readonly string $computerName,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $lastActivityAt,
    ) {
        parent::__construct();
    }

    public static function start(
        SessionId $sessionId,
        UserId $userId,
        string $ipAddress,
        string $userAgent,
        string $computerName,
        ?DateTimeImmutable $now = null,
    ): self {
        $ipAddress = trim($ipAddress);
        $userAgent = trim($userAgent);
        $computerName = trim($computerName);

        if ($ipAddress === '') {
            throw new InvalidArgumentException(
                'IP address cannot be empty.'
            );
        }

        if ($userAgent === '') {
            throw new InvalidArgumentException(
                'User agent cannot be empty.'
            );
        }

        if ($computerName === '') {
            $computerName = 'UNKNOWN';
        }

        $now ??= new DateTimeImmutable();

        return new self(
            sessionId: $sessionId,
            userId: $userId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            computerName: $computerName,
            createdAt: $now,
            lastActivityAt: $now,
        );
    }

    public static function restore(
        SessionId $sessionId,
        UserId $userId,
        string $ipAddress,
        string $userAgent,
        string $computerName,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $lastActivityAt,
        ?DateTimeImmutable $revokedAt = null,
    ): self {
        $session = new self(
            sessionId: $sessionId,
            userId: $userId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            computerName: $computerName,
            createdAt: $createdAt,
            lastActivityAt: $lastActivityAt,
        );

        $session->revokedAt = $revokedAt;

        return $session;
    }

    public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function ipAddress(): string
    {
        return $this->ipAddress;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function computerName(): string
    {
        return $this->computerName;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastActivityAt(): DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }

    public function touch(?DateTimeImmutable $now = null): void
    {
        if (!$this->isActive()) {
            throw new LogicException(
                'Cannot update activity of a revoked session.'
            );
        }

        $this->lastActivityAt = $now ?? new DateTimeImmutable();
    }

    public function revoke(?DateTimeImmutable $now = null): void
    {
        if (!$this->isActive()) {
            return;
        }

        $this->revokedAt = $now ?? new DateTimeImmutable();
    }
}