<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use StageArt\Application\Rehearsal\RehearsalDateTime;
use StageArt\Domain\TimetableItem\TimetableItem;

/**
 * PR #7 R11: how a Timetable Item's start/end (and the Production
 * Schedule's from/to range) are read from the API
 * (docs/20-RehearsalManagementScreen.md §24).
 *
 * Timetable times are Asia/Tokyo business times (TimetableItem::TIMEZONE).
 * The accepted format and rules are the Rehearsal ones (RehearsalDateTime,
 * §23.3), with the zone fixed to Asia/Tokyo:
 * - with an offset (`+09:00`, `Z`, `-05:00`, ...) the value is that
 *   instant, whatever the offset is;
 * - without an offset it is Asia/Tokyo wall-clock time;
 * - anything else is rejected (422), never guessed.
 *
 * The result is always expressed in Asia/Tokyo.
 */
final class TimetableItemDateTime
{
    public static function zone(): DateTimeZone
    {
        return new DateTimeZone(TimetableItem::TIMEZONE);
    }

    public static function parseOptional(?string $value): ?DateTimeImmutable
    {
        return RehearsalDateTime::parseOptional($value, self::zone());
    }

    public static function parseRequired(?string $value, string $fieldName): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("Timetable Item {$fieldName} is required.");
        }

        /** @var DateTimeImmutable $parsed non-null for a non-empty value */
        $parsed = self::parseOptional($value);

        return $parsed;
    }
}
