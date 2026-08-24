<?php

declare(strict_types=1);

namespace StageArt\Domain\Production;

use InvalidArgumentException;

/**
 * StageArt Web First Phase 2 (docs/03-PublicPageURLAndPublicationSchedule.md):
 * the public URL identifier for `stageart.top/{organization-slug}/
 * {production-slug}`. Unique StageArt-wide, not merely within its
 * Organization - Production has no direct `organizationId` today (it
 * reaches its Organization via `Production -> Project -> Organization`;
 * see this Phase's report), so a per-Organization DB constraint would
 * require denormalizing `organization_id` onto `stageart_productions`
 * purely for that constraint. Global uniqueness avoids that
 * denormalization while the public URL still reads as hierarchical
 * (nested under the Organization's own slug) even though the slug
 * values themselves don't need to repeat across different
 * Organizations. Same shape rules as OrganizationSlug, kept as a
 * separate VO rather than a shared base to match this codebase's
 * existing per-aggregate Name/Status VO convention.
 */
final class ProductionSlug
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
                'Production slug must be between ' . self::MIN_LENGTH . ' and ' . self::MAX_LENGTH . ' characters.'
            );
        }

        if (preg_match(self::PATTERN, $trimmed) !== 1) {
            throw new InvalidArgumentException(
                'Production slug must contain only lowercase letters, numbers, and single hyphens (no leading, trailing, or double hyphens).'
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
