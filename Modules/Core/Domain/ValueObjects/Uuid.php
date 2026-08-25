<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

final readonly class Uuid
{
    private function __construct(
        private UuidInterface $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(
            RamseyUuid::uuid4(),
        );
    }

    public static function fromString(string $value): self
    {
        if (!RamseyUuid::isValid($value)) {
            throw new InvalidArgumentException(
                'The provided value is not a valid UUID.',
            );
        }

        return new self(
            RamseyUuid::fromString($value),
        );
    }

    public function value(): string
    {
        return $this->value->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->value();
    }
}