<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * PR #7 R10: how a Rehearsal's start/end/response deadline are read from
 * the API (docs/20-RehearsalManagementScreen.md §23.3).
 *
 * - Accepted: ISO 8601 `YYYY-MM-DDTHH:MM[:SS[.fraction]]` with an optional
 *   offset (`Z`, `+09:00`, `-0500`, ...). A space instead of `T` is also
 *   accepted, as before.
 * - With an offset, the value is that instant, whatever the offset is.
 * - Without an offset, the value is wall-clock time in the Rehearsal's own
 *   timezone (default Asia/Tokyo) - the contract existing clients relied
 *   on, since such values were stored and read back unchanged.
 * - Anything else (`not a date`, `2026-02-30T10:00`, `+3 days`, ...) is
 *   rejected (422), never guessed.
 *
 * The result is always expressed in the Rehearsal's timezone, so the same
 * instant sent with different offsets is the same value everywhere after
 * this point: the stored wall-clock time (WordPressRehearsalRepository),
 * the "date cannot change once confirmed" check (Rehearsal::
 * updateBasicInfo()) and the API response.
 */
final class RehearsalDateTime
{
    public const DEFAULT_TIMEZONE = 'Asia/Tokyo';

    private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?$/';

    /**
     * The Rehearsal's timezone: the given IANA name, or Asia/Tokyo when
     * none is given. An unknown name is rejected.
     */
    public static function zone(?string $timezone): DateTimeZone
    {
        if ($timezone === null || trim($timezone) === '') {
            return new DateTimeZone(self::DEFAULT_TIMEZONE);
        }

        try {
            return new DateTimeZone($timezone);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid timezone: {$timezone}");
        }
    }

    public static function parseOptional(?string $value, DateTimeZone $zone): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match(self::PATTERN, $value, $m) !== 1) {
            throw new InvalidArgumentException("Invalid date/time value: {$value}");
        }

        [, $year, $month, $day, $hour, $minute] = $m;
        $second = $m[6] ?? '';

        if (! checkdate((int) $month, (int) $day, (int) $year)
            || (int) $hour > 23
            || (int) $minute > 59
            || ($second !== '' && (int) $second > 59)
        ) {
            throw new InvalidArgumentException("Invalid date/time value: {$value}");
        }

        try {
            // The zone argument applies only when $value has no offset.
            $parsed = new DateTimeImmutable($value, $zone);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid date/time value: {$value}");
        }

        return $parsed->setTimezone($zone);
    }

    public static function parseRequired(?string $value, DateTimeZone $zone, string $fieldName): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("Rehearsal {$fieldName} is required.");
        }

        /** @var DateTimeImmutable $parsed non-null for a non-empty value */
        $parsed = self::parseOptional($value, $zone);

        return $parsed;
    }
}
