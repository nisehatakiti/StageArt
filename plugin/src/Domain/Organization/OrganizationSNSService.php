<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use InvalidArgumentException;

/**
 * docs/04-DomainModel/OrganizationSNS.md "Initial Supported Services":
 * X and Instagram are the priority services; OTHER covers anything else
 * ("将来的には、YouTube等の追加サービスを許容する") without this VO needing
 * a new constant for every future service name.
 */
final class OrganizationSNSService
{
    public const X = 'X';
    public const INSTAGRAM = 'INSTAGRAM';
    public const FACEBOOK = 'FACEBOOK';
    public const OTHER = 'OTHER';

    private const VALID = [
        self::X,
        self::INSTAGRAM,
        self::FACEBOOK,
        self::OTHER,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid OrganizationSNSService: {$value}");
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
