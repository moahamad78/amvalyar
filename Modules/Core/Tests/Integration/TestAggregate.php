<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Integration;

use Modules\Core\Domain\Aggregates\AggregateRoot;

final class TestAggregate extends AggregateRoot
{
    public function __construct()
    {
        parent::__construct();
    }

    public function triggerEvent(string $message): void
    {
        $this->recordEvent(
            new TestEvent($message)
        );
    }
}