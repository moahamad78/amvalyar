<?php

declare(strict_types=1);

namespace Modules\Core\Application\Events;

use Modules\Core\Domain\Events\DomainEvent;

interface EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;
}