<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\JournalEntry;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use StageArt\Application\JournalEntry\JournalEntryResult;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\JournalEntry\AccountingDate;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

/**
 * PR #6 review N1 (JournalEntry.md "Journal Date", Version 1.1): the 仕訳日
 * is the JST calendar date of the Journal Date instant - never the UTC
 * date its stored text shows.
 */
final class AccountingDateTest extends TestCase
{
    private string $defaultTimezone;

    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }

    /**
     * @dataProvider boundaries
     */
    public function test_the_accounting_date_is_the_jst_date_of_the_instant(string $utc, string $date, string $month): void
    {
        $instant = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

        $this->assertSame($date, AccountingDate::of($instant));
        $this->assertSame($month, AccountingDate::monthOf($instant));
    }

    /**
     * @return array<string, array{string, string, string}> UTC instant, JST date, JST month
     */
    public static function boundaries(): array
    {
        return [
            'just before JST midnight' => ['2026-08-20 14:59:59', '2026-08-20', '2026-08'],
            'JST midnight' => ['2026-08-20 15:00:00', '2026-08-21', '2026-08'],
            'JST morning still the previous UTC date' => ['2026-08-20 23:30:00', '2026-08-21', '2026-08'],
            'UTC midnight is JST 09:00 of the same date' => ['2026-08-21 00:00:00', '2026-08-21', '2026-08'],
            'last second of a month (JST)' => ['2026-04-30 14:59:59', '2026-04-30', '2026-04'],
            'first second of the next month (JST)' => ['2026-04-30 15:00:00', '2026-05-01', '2026-05'],
            'February in a leap year' => ['2028-02-28 15:00:00', '2028-02-29', '2028-02'],
            'Japanese fiscal year end, 03-31 23:59:59 JST' => ['2026-03-31 14:59:59', '2026-03-31', '2026-03'],
            'Japanese fiscal year start, 04-01 00:00 JST' => ['2026-03-31 15:00:00', '2026-04-01', '2026-04'],
            'calendar year end, 12-31 23:59:59 JST' => ['2026-12-31 14:59:59', '2026-12-31', '2026-12'],
            'calendar year start, 01-01 00:00 JST' => ['2026-12-31 15:00:00', '2027-01-01', '2027-01'],
        ];
    }

    /**
     * The same instant, whatever zone it was written in and whatever PHP's
     * default zone is (WordPress sets UTC; a host or test may not).
     */
    public function test_the_zone_an_instant_carries_and_the_php_default_zone_do_not_matter(): void
    {
        $instants = [
            new DateTimeImmutable('2026-03-31 15:30:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-04-01 00:30:00', new DateTimeZone('Asia/Tokyo')),
            new DateTimeImmutable('2026-03-31 08:30:00', new DateTimeZone('America/Los_Angeles')),
            new DateTime('2026-04-01T00:30:00+09:00'),
        ];

        foreach (['UTC', 'Asia/Tokyo', 'America/New_York'] as $default) {
            date_default_timezone_set($default);

            foreach ($instants as $instant) {
                $this->assertSame('2026-04-01', AccountingDate::of($instant), "default zone {$default}");
                $this->assertSame('2026-04', AccountingDate::monthOf($instant), "default zone {$default}");
            }
        }
    }

    public function test_a_journal_entry_and_its_result_carry_the_jst_accounting_date(): void
    {
        $entry = $this->entryAt(new DateTimeImmutable('2026-03-31 15:30:00', new DateTimeZone('UTC')));

        $this->assertSame('2026-04-01', $entry->accountingDate());

        $result = JournalEntryResult::fromDomain($entry)->toArray();
        $this->assertSame('2026-03-31T15:30:00+00:00', $result['journal_date'], 'journal_date stays the instant (日時)');
        $this->assertSame('2026-04-01', $result['accounting_date'], 'accounting_date is the 仕訳日 (日付, JST)');
    }

    private function entryAt(DateTimeImmutable $journalDate): JournalEntry
    {
        $person = PersonId::generate();

        return JournalEntry::create(
            OrganizationId::generate(),
            null,
            $journalDate,
            'N1 boundary entry',
            [
                JournalEntryLine::create(AccountId::generate(), DebitCredit::debit(), 1000),
                JournalEntryLine::create(AccountId::generate(), DebitCredit::credit(), 1000),
            ],
            $person
        );
    }
}
