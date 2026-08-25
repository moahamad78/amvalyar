<?php

declare(strict_types=1);

namespace App\Services\AssetCode;

use InvalidArgumentException;

final readonly class AssetCodePolicy
{
    public function __construct(
        public string $prefix,
        public int $padding = 6,
        public string $separator = '-',
    ) {
        if (trim($this->prefix) === '') {
            throw new InvalidArgumentException(
                'Asset code prefix cannot be empty.'
            );
        }

        if ($this->padding < 1 || $this->padding > 12) {
            throw new InvalidArgumentException(
                'Asset code padding must be between 1 and 12.'
            );
        }
    }

    public function format(int $sequence): string
    {
        if ($sequence < 1) {
            throw new InvalidArgumentException(
                'Asset code sequence must be greater than zero.'
            );
        }

        return $this->prefix
            . $this->separator
            . str_pad(
                (string) $sequence,
                $this->padding,
                '0',
                STR_PAD_LEFT
            );
    }

    public function parseSequence(string $code): ?int
    {
        $pattern =
            '/^'
            . preg_quote(
                $this->prefix . $this->separator,
                '/'
            )
            . '(\d{'
            . $this->padding
            . ',})$/';

        if (
            preg_match(
                $pattern,
                trim($code),
                $matches
            ) !== 1
        ) {
            return null;
        }

        $sequence = (int) $matches[1];

        return $sequence > 0
            ? $sequence
            : null;
    }

    public function likePattern(): string
    {
        return $this->prefix
            . $this->separator
            . '%';
    }
}