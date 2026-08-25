<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Aggregates;

use Modules\Core\Domain\Entities\Entity;

abstract class AggregateRoot extends Entity
{
    /**
     * @var array<object>
     */
    private array $domainEvents = [];

    /**
     * ثبت یک رویداد دامنه
     */
    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * دریافت رویدادهای ثبت‌شده
     *
     * @return array<object>
     */
    public function domainEvents(): array
    {
        return $this->domainEvents;
    }

    /**
     * پاک‌کردن رویدادهای ثبت‌شده
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;

        $this->domainEvents = [];

        return $events;
    }

    /**
     * بررسی وجود رویداد
     */
    public function hasDomainEvents(): bool
    {
        return $this->domainEvents !== [];
    }
}