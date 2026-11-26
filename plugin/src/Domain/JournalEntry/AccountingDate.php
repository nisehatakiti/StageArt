<?php

declare(strict_types=1);

namespace StageArt\Domain\JournalEntry;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * JournalEntry.md "Journal Date" (Version 1.1, PR #6 review N1): the
 * accounting date - the 仕訳日 / 取引日 a Journal Entry counts on - is the
 * calendar date in Japan Standard Time (DateTime.md: Version 1.0 uses JST)
 * of the entry's Journal Date.
 *
 * The Journal Date itself is a point in time (日時): it is stored as UTC
 * (DATETIME, as every StageArt timestamp) and converted here, never by
 * cutting the UTC text, so 2026-03-31 23:30 JST (14:30 UTC) is 2026-03-31
 * and 2026-04-01 00:30 JST (2026-03-31 15:30 UTC) is 2026-04-01 whatever
 * the PHP default time zone. Accounting periods, monthly and yearly totals
 * take the date (日付) from here.
 */
final class AccountingDate
{
    public const TIMEZONE = 'Asia/Tokyo';

    private function __construct()
    {
    }

    /** The JST calendar date of $instant, as Y-m-d. */
    public static function of(DateTimeInterface $instant): string
    {
        return self::inJst($instant)->format('Y-m-d');
    }

    /** The JST calendar month of $instant, as Y-m (the monthly totals' key). */
    public static function monthOf(DateTimeInterface $instant): string
    {
        return self::inJst($instant)->format('Y-m');
    }

    private static function inJst(DateTimeInterface $instant): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone(self::TIMEZONE));
    }
}
