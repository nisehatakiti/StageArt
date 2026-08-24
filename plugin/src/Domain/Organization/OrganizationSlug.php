<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use InvalidArgumentException;

/**
 * StageArt Web First Phase 2 (docs/03-PublicPageURLAndPublicationSchedule.md):
 * the public URL identifier for `stageart.top/{organization-slug}`.
 * Validated here (Domain-level, framework-independent), enforced unique
 * at the Database level (see Installer.php's UNIQUE KEY) - this VO only
 * guards shape, not uniqueness, matching this codebase's existing
 * Name/Status VOs' scope (uniqueness is always an Application/
 * Infrastructure concern here, never re-implemented inside a VO).
 *
 * A large permanently-maintained reserved-word list against the Web
 * app's own top-level route names was considered and rejected: the Web
 * client deliberately serves public pages under an `/o/{slug}` prefix
 * (see this Phase's report), which structurally cannot collide with
 * `login`/`home`/`discover`/etc. regardless of what slug a user picks -
 * this list is kept small and is pure hygiene, not collision avoidance.
 */
final class OrganizationSlug
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 64;
    private const PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
    private const RESERVED = ['admin', 'api', 'null', 'undefined', 'test', 'www'];

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (mb_strlen($trimmed) < self::MIN_LENGTH || mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                'Organization slug must be between ' . self::MIN_LENGTH . ' and ' . self::MAX_LENGTH . ' characters.'
            );
        }

        if (preg_match(self::PATTERN, $trimmed) !== 1) {
            throw new InvalidArgumentException(
                'Organization slug must contain only lowercase letters, numbers, and single hyphens (no leading, trailing, or double hyphens).'
            );
        }

        if (in_array($trimmed, self::RESERVED, true)) {
            throw new InvalidArgumentException("\"{$trimmed}\" is a reserved slug and cannot be used.");
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
