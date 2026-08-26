<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use InvalidArgumentException;

/**
 * StageArt Web First Phase 2 / Public Page Architecture phase
 * (docs/03-PublicPageURLAndPublicationSchedule.md): the public URL
 * identifier for `stageart.top/{organization-slug}`. Validated here
 * (Domain-level, framework-independent), enforced unique at the
 * Database level (see Installer.php's UNIQUE KEY) - this VO only
 * guards shape, not uniqueness, matching this codebase's existing
 * Name/Status VOs' scope (uniqueness is always an Application/
 * Infrastructure concern here, never re-implemented inside a VO).
 *
 * RESERVED must list every one of the Web app's own top-level route
 * segments (files and directories directly under mobile-rn/src/app/):
 * Organization public pages now resolve at root level
 * (`/{organization-slug}`, matching stageart.top's intended path shape)
 * rather than under an `/o/` prefix, so a slug equal to a real route
 * name (e.g. "login") would otherwise be permanently unreachable -
 * Expo Router/React Navigation always resolves a literal path segment
 * before a dynamic one at the same level. Production slugs (the
 * *second* URL segment, `/{org-slug}/{production-slug}`) do not need
 * this protection - they only resolve once the first segment has
 * already failed to match any top-level route, so they cannot shadow
 * anything (see ProductionSlug.php).
 */
final class OrganizationSlug
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 64;
    private const PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
    private const RESERVED = [
        'admin', 'api', 'null', 'undefined', 'test', 'www',
        // Web app top-level route segments (mobile-rn/src/app/):
        'home', 'join', 'login', 'profile', 'discover', 'register',
        'set-name', 'favorites', 'verify-email', 'reset-password',
        'forgot-password', 'viewing-history', 'discover-productions',
        'discover-organizations', 'registration-pending', 'o',
        'organizations', 'production-invite', 'production',
        'participating-productions', 'index', 'sitemap', 'not-found',
    ];

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
