<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressJournalEntryRepository;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #6 review N1: journal_date is stored as UTC (the existing DATETIME
 * column, unchanged) whatever zone the instant carries or PHP's default
 * zone is, and read back as UTC - so the JST 仕訳日 derived from it
 * (AccountingDate) is the same before and after the round trip.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressJournalEntryRepositoryTest extends TestCase
{
    /** @var array<string, array<string, mixed>> table => the row last inserted */
    private array $rows = [];

    /** @var array<int, array<string, mixed>> */
    private array $lineRows = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
    }

    private function wpdb(): FakeWpdb
    {
        return new \wpdb(function (string $method, array $args) {
            if ($method === 'insert') {
                if (str_ends_with($args[0], 'stageart_journal_entry_lines')) {
                    $this->lineRows[] = $args[1];
                } else {
                    $this->rows[$args[0]] = $args[1];
                }

                return 1;
            }

            if ($method === 'get_row') {
                return $this->rows['wp_stageart_journal_entries'] ?? null;
            }

            if ($method === 'get_results') {
                return $this->lineRows;
            }

            return FakeWpdbDefault::VALUE;
        });
    }

    private function entryAt(DateTimeImmutable $journalDate): JournalEntry
    {
        return JournalEntry::create(
            OrganizationId::generate(),
            null,
            $journalDate,
            'N1 round trip',
            [
                JournalEntryLine::create(AccountId::generate(), DebitCredit::debit(), 1000),
                JournalEntryLine::create(AccountId::generate(), DebitCredit::credit(), 1000),
            ],
            PersonId::generate()
        );
    }

    /**
     * @dataProvider writtenIn
     */
    public function test_the_journal_date_is_stored_as_utc_and_read_back_as_the_same_instant(string $defaultZone, DateTimeImmutable $journalDate): void
    {
        date_default_timezone_set($defaultZone);
        $repository = new WordPressJournalEntryRepository($this->wpdb());
        $entry = $this->entryAt($journalDate);

        $repository->save($entry);

        $this->assertSame('2026-03-31 15:30:00', $this->rows['wp_stageart_journal_entries']['journal_date'], 'UTC wall clock in the DATETIME column');

        $read = $repository->findById($entry->id());

        $this->assertSame($journalDate->getTimestamp(), $read->journalDate()->getTimestamp());
        $this->assertSame('2026-04-01', $read->accountingDate(), 'JST 00:30 on April 1st');
    }

    /**
     * @return array<string, array{string, DateTimeImmutable}>
     */
    public static function writtenIn(): array
    {
        $utc = new DateTimeImmutable('2026-03-31 15:30:00', new DateTimeZone('UTC'));

        return [
            'UTC instant, UTC default (WordPress)' => ['UTC', $utc],
            'UTC instant, JST default' => ['Asia/Tokyo', $utc],
            'JST instant, UTC default' => ['UTC', $utc->setTimezone(new DateTimeZone('Asia/Tokyo'))],
            'JST instant, New York default' => ['America/New_York', $utc->setTimezone(new DateTimeZone('Asia/Tokyo'))],
        ];
    }

    /**
     * Rows written before this change hold the UTC wall clock too (WordPress
     * runs PHP in UTC): they are read as UTC and keep their meaning.
     */
    public function test_an_existing_row_is_read_as_utc_whatever_the_default_zone(): void
    {
        date_default_timezone_set('Asia/Tokyo');
        $repository = new WordPressJournalEntryRepository($this->wpdb());
        $entry = $this->entryAt(new DateTimeImmutable('2026-08-20 10:00:00', new DateTimeZone('UTC')));
        $repository->save($entry);
        $this->rows['wp_stageart_journal_entries']['journal_date'] = '2026-08-20 16:00:00';

        $read = $repository->findById($entry->id());

        $this->assertSame('2026-08-20T16:00:00+00:00', $read->journalDate()->format(DATE_ATOM));
        $this->assertSame('2026-08-21', $read->accountingDate());
    }
}
