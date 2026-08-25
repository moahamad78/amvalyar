<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Security\ValueObjects;

use InvalidArgumentException;

final readonly class SessionId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                'Session ID cannot be empty.'
            );
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals(
            $this->value,
            $other->value
        );
    }

    public function __toString(): string
    {
        return $this->value;
    }
}