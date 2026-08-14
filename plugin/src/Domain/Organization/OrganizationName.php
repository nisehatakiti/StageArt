<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use InvalidArgumentException;

final class OrganizationName
{
    private const MAX_LENGTH = 255;

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Organization name must not be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Organization name must not exceed ' . self::MAX_LENGTH . ' characters.');
        }

        $this->value = $trimmed;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
