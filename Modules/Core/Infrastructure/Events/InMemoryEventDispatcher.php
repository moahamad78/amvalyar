<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Events;

use Modules\Core\Application\Events\EventDispatcherInterface;
use Modules\Core\Domain\Events\DomainEvent;

final class InMemoryEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<DomainEvent>
     */
    private array $dispatchedEvents = [];

    public function dispatch(DomainEvent $event): void
    {
        $this->dispatchedEvents[] = $event;
    }

    /**
     * @return array<DomainEvent>
     */
    public function dispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    public function clear(): void
    {
        $this->dispatchedEvents = [];
    }
}