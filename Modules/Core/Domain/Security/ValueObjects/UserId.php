<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Security\ValueObjects;

use InvalidArgumentException;

final readonly class UserId
{
    private function __construct(
        private int $value,
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(
                'User ID must be greater than zero.'
            );
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}