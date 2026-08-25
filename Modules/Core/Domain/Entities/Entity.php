<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Entities;

use DateTimeImmutable;
use Modules\Core\Domain\ValueObjects\Uuid;

abstract class Entity
{
    private readonly Uuid $id;

    private readonly DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    protected function __construct(
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::generate();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    protected function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}