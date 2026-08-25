<?php

declare(strict_types=1);

namespace Modules\Core\Application\Events;

use Modules\Core\Domain\Aggregates\AggregateRoot;

final class AggregateEventPublisher
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function publish(AggregateRoot $aggregate): void
    {
        foreach ($aggregate->releaseEvents() as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}