<?php

declare(strict_types=1);

namespace StageArt\Domain\Membership;

use InvalidArgumentException;

/**
 * 代理人の管理領域 (docs/03-PublicPageURLAndPublicationSchedule.md
 * 「代理人設定」): the Organization Owner delegates each area to an
 * ORGANIZATION_MANAGER separately - one delegate may hold several areas,
 * one area may be held by several delegates. The delegate's Role itself
 * stays ORGANIZATION_MANAGER (docs/04-DomainModel/Organization.md's
 * "一つのMembershipへ複数Roleを直接付与する構造は基本設計としない"); the
 * areas are attached to that one Membership, not extra Roles.
 *
 * The Owner holds every area implicitly and is never stored here.
 */
final class DelegateArea
{
    public const MEMBERS = 'MEMBERS';
    public const PRODUCTIONS = 'PRODUCTIONS';
    public const ACCOUNTING = 'ACCOUNTING';

    private const VALID = [self::MEMBERS, self::PRODUCTIONS, self::ACCOUNTING];

    private function __construct()
    {
    }

    /** @return string[] in display order */
    public static function all(): array
    {
        return self::VALID;
    }

    public static function isValid(string $area): bool
    {
        return in_array($area, self::VALID, true);
    }

    /**
     * Validates a requested set of areas: each must be a known area and
     * none may appear twice. Returns them in display order.
     *
     * @param mixed[] $areas
     * @return string[]
     */
    public static function normalize(array $areas): array
    {
        $seen = [];

        foreach ($areas as $area) {
            if (! is_string($area) || ! self::isValid($area)) {
                throw new InvalidArgumentException('Unknown delegate area: ' . (is_scalar($area) ? (string) $area : gettype($area)) . '.');
            }

            if (isset($seen[$area])) {
                throw new InvalidArgumentException("Delegate area {$area} is listed more than once.");
            }

            $seen[$area] = true;
        }

        return array_values(array_filter(self::VALID, static fn (string $area): bool => isset($seen[$area])));
    }
}
