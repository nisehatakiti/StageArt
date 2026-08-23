<?php

declare(strict_types=1);

namespace StageArt\Domain\Production;

use InvalidArgumentException;

/**
 * Production.md lists Production Name among Production Information but
 * does not state whether it is required (unlike Project.md, which
 * explicitly says a Project name is optional). A Production without any
 * name is not practically usable, so this slice treats it as required,
 * matching OrganizationName's validation shape - this is a disclosed
 * implementation judgment, not an explicit Blueprint requirement.
 */
final class ProductionName
{
    private const MAX_LENGTH = 255;

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Production name must not be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Production name must not exceed ' . self::MAX_LENGTH . ' characters.');
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
