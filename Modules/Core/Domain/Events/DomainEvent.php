<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

use DateTimeImmutable;

abstract class DomainEvent
{
    private readonly DateTimeImmutable $occurredAt;

    protected function __construct()
    {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}