<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Integration;

use Modules\Core\Domain\Events\DomainEvent;

final class TestEvent extends DomainEvent
{
    public function __construct(
        public readonly string $message,
    ) {
        parent::__construct();
    }
}