<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\JournalEntry;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\JournalEntry\JournalEntryStatus;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class JournalEntryTest extends TestCase
{
    private function balancedEntry(): JournalEntry
    {
        $expenseAccount = AccountId::generate();
        $payableAccount = AccountId::generate();

        return JournalEntry::create(
            OrganizationId::generate(),
            ProductionId::generate(),
            new DateTimeImmutable(),
            'Venue cost',
            [
                JournalEntryLine::create($expenseAccount, DebitCredit::debit(), 30000),
                JournalEntryLine::create($payableAccount, DebitCredit::credit(), 30000),
            ],
            PersonId::generate()
        );
    }

    public function test_create_starts_in_draft(): void
    {
        $entry = $this->balancedEntry();

        $this->assertSame(JournalEntryStatus::DRAFT, $entry->status()->toString());
        $this->assertFalse($entry->isPosted());
    }

    public function test_post_succeeds_when_debits_equal_credits(): void
    {
        $entry = $this->balancedEntry();
        $poster = PersonId::generate();

        $entry->post($poster);

        $this->assertTrue($entry->isPosted());
        $this->assertTrue($entry->postedBy()->equals($poster));
        $this->assertNotNull($entry->postedAt());
    }

    public function test_post_rejects_unbalanced_entry(): void
    {
        $entry = JournalEntry::create(
            OrganizationId::generate(),
            null,
            new DateTimeImmutable(),
            'Unbalanced',
            [
                JournalEntryLine::create(AccountId::generate(), DebitCredit::debit(), 30000),
                JournalEntryLine::create(AccountId::generate(), DebitCredit::credit(), 20000),
            ],
            PersonId::generate()
        );

        $this->expectException(InvalidArgumentException::class);

        $entry->post(PersonId::generate());
    }

    public function test_post_rejects_missing_credit_side(): void
    {
        $entry = JournalEntry::create(
            OrganizationId::generate(),
            null,
            new DateTimeImmutable(),
            'Debit only',
            [JournalEntryLine::create(AccountId::generate(), DebitCredit::debit(), 100)],
            PersonId::generate()
        );

        $this->expectException(InvalidArgumentException::class);

        $entry->post(PersonId::generate());
    }

    public function test_post_twice_is_rejected(): void
    {
        $entry = $this->balancedEntry();
        $entry->post(PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $entry->post(PersonId::generate());
    }

    public function test_create_reversal_of_posted_entry_has_opposite_lines_and_reversed_status(): void
    {
        $entry = $this->balancedEntry();
        $entry->post(PersonId::generate());
        $originalLines = $entry->lines();

        $reversal = JournalEntry::createReversalOf($entry, PersonId::generate());

        $this->assertSame(JournalEntryStatus::REVERSED, $reversal->status()->toString());
        $this->assertTrue($reversal->reversalOfJournalEntryId()->equals($entry->id()));
        $this->assertCount(count($originalLines), $reversal->lines());

        foreach ($originalLines as $index => $originalLine) {
            $reversedLine = $reversal->lines()[$index];
            $this->assertTrue($reversedLine->accountId()->equals($originalLine->accountId()));
            $this->assertSame($originalLine->amount(), $reversedLine->amount());
            $this->assertNotSame($originalLine->debitCredit()->toString(), $reversedLine->debitCredit()->toString());
        }
    }

    public function test_create_reversal_of_non_posted_entry_is_rejected(): void
    {
        $entry = $this->balancedEntry();

        $this->expectException(InvalidArgumentException::class);

        JournalEntry::createReversalOf($entry, PersonId::generate());
    }

    public function test_mark_reversed_requires_posted_status(): void
    {
        $entry = $this->balancedEntry();

        $this->expectException(InvalidArgumentException::class);

        $entry->markReversed(PersonId::generate());
    }
}
