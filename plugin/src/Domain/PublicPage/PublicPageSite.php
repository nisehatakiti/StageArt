<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

use InvalidArgumentException;

/**
 * Which public page a set of display settings belongs to. Matches
 * StageArtCore's `$site` argument ('organization' / 'production'), which
 * selects the theme preset list and the hero preset manifest.
 */
final class PublicPageSite
{
    public const ORGANIZATION = 'organization';
    public const PRODUCTION = 'production';

    private const VALID = [self::ORGANIZATION, self::PRODUCTION];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid PublicPageSite: {$value}");
        }

        $this->value = $value;
    }

    public static function organization(): self
    {
        return new self(self::ORGANIZATION);
    }

    public static function production(): self
    {
        return new self(self::PRODUCTION);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isProduction(): bool
    {
        return $this->value === self::PRODUCTION;
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
