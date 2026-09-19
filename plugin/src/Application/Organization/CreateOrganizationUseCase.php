<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * Creating an Organization always creates its first Membership too: per
 * Organization.md, Organization has no OwnerId field, so Ownership can
 * only exist by way of a Membership+RoleKey. Without this, a freshly
 * created Organization would be unmanageable by anyone.
 *
 * The Organization save and the Owner Membership save run inside one
 * TransactionManager-wrapped operation: without this, a failure saving
 * the Membership after the Organization already saved successfully
 * would leave an Organization with zero OWNER Memberships on disk,
 * violating the Organization Owner Invariant (exactly one OWNER
 * Membership per Organization) the moment it's created. Person lookup/
 * creation stays outside the transaction - a Person existing without
 * ever gaining an Organization is not an invariant violation.
 *
 * StageArt Phase 1 (OrganizationSetupPolicy.md "Step 3: Accounting
 * Opening Balance"): when accountingEnabled is requested with a
 * non-zero opening 現金/預金 balance, this Use Case also creates the two
 * ASSET Accounts, a 元入金 (Opening Balance Equity) EQUITY Account to
 * balance them, and one POSTED JournalEntry recording the opening
 * balance - all inside the same transaction as the Organization itself,
 * so a freshly created Organization is never left in a state where
 * Accounting is ON but its very first Actual entry failed to save.
 * Posted immediately (not left DRAFT like ConfirmExpenseUseCase's
 * entries) since there is no separate manager-review step for an
 * Organization's own opening balance - the value entered here IS the
 * confirmed starting Actual (Blueprint: "この入力値をAccounting開始時点の
 * Opening Balanceとして扱う").
 */
final class CreateOrganizationUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private PersonRepositoryInterface $people;
    private MembershipRepositoryInterface $memberships;
    private AccountRepositoryInterface $accounts;
    private JournalEntryRepositoryInterface $journalEntries;
    private TransactionManagerInterface $transactions;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        PersonRepositoryInterface $people,
        MembershipRepositoryInterface $memberships,
        AccountRepositoryInterface $accounts,
        JournalEntryRepositoryInterface $journalEntries,
        TransactionManagerInterface $transactions
    ) {
        $this->organizations = $organizations;
        $this->people = $people;
        $this->memberships = $memberships;
        $this->accounts = $accounts;
        $this->journalEntries = $journalEntries;
        $this->transactions = $transactions;
    }

    public function execute(CreateOrganizationCommand $command): OrganizationResult
    {
        $slug = new OrganizationSlug($command->slug);

        // StageArt Web First Phase 2: an explicit pre-check for a clean
        // 422 in the common case; the DB's own UNIQUE KEY slug (slug)
        // constraint (Installer.php) remains the ultimate backstop
        // against the rare concurrent-write race this check alone
        // cannot fully close.
        if ($this->organizations->findBySlug($slug->toString()) !== null) {
            throw new OrganizationSlugAlreadyTakenException($slug->toString());
        }

        if ($command->openingCashBalance !== null && $command->openingCashBalance < 0) {
            throw new InvalidArgumentException('opening_cash_balance must not be negative.');
        }

        if ($command->openingBankBalance !== null && $command->openingBankBalance < 0) {
            throw new InvalidArgumentException('opening_bank_balance must not be negative.');
        }

        $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

        if (! $person) {
            $person = Person::create($command->requestedByWordPressUserId);
            $this->people->save($person);
        }

        return $this->transactions->run(function () use ($command, $person, $slug): OrganizationResult {
            $organization = Organization::create(
                new OrganizationName($command->name),
                $command->type,
                $command->description,
                $slug,
                $command->accountingEnabled
            );

            // docs/03-PublicPageURLAndPublicationSchedule.md: "Organization
            // の作成・団体情報の保存に、非公開状態を設けない。保存が成功した
            // Organizationは保存と同時に公開状態とする" - a slug is always
            // present here (CreateOrganizationCommand::$slug is required),
            // so Organization::publish()'s own slug precondition always
            // holds.
            $organization->publish();

            $this->organizations->save($organization);

            $membership = Membership::createOwnerMembership($organization->id(), $person->id());
            $this->memberships->save($membership);

            if ($command->accountingEnabled) {
                $this->recordOpeningBalance($organization, $person->id(), $command->openingCashBalance, $command->openingBankBalance);
            }

            return OrganizationResult::fromDomain($organization, RoleKey::owner());
        });
    }

    private function recordOpeningBalance(
        Organization $organization,
        PersonId $createdBy,
        ?int $cashBalance,
        ?int $bankBalance
    ): void {
        $cash = $cashBalance ?? 0;
        $bank = $bankBalance ?? 0;

        if ($cash <= 0 && $bank <= 0) {
            // Accounting turned ON with no starting balance entered
            // (OrganizationSetupPolicy.md's Step 3 is itself optional
            // input, not a required amount) - the two Accounts below are
            // still useful to have ready, but there is nothing to post.
            $this->createStandardAccounts($organization);

            return;
        }

        [$cashAccount, $bankAccount, $equityAccount] = $this->createStandardAccounts($organization);

        $lines = [];

        if ($cash > 0) {
            $lines[] = JournalEntryLine::create($cashAccount->id(), DebitCredit::debit(), $cash, '開始残高');
        }

        if ($bank > 0) {
            $lines[] = JournalEntryLine::create($bankAccount->id(), DebitCredit::debit(), $bank, '開始残高');
        }

        $lines[] = JournalEntryLine::create($equityAccount->id(), DebitCredit::credit(), $cash + $bank, '開始残高');

        $journalEntry = JournalEntry::create(
            $organization->id(),
            null,
            new DateTimeImmutable(),
            '開始残高',
            $lines,
            $createdBy,
            'OrganizationAccountingEnabled',
            $organization->id()->toString()
        );

        $journalEntry->post($createdBy);

        $this->journalEntries->save($journalEntry);
    }

    /**
     * @return array{0: Account, 1: Account, 2: Account} [現金, 預金, 元入金]
     */
    private function createStandardAccounts(Organization $organization): array
    {
        $cashAccount = Account::create($organization->id(), '現金', AccountType::fromString(AccountType::ASSET));
        $bankAccount = Account::create($organization->id(), '預金', AccountType::fromString(AccountType::ASSET));
        $equityAccount = Account::create($organization->id(), '元入金', AccountType::fromString(AccountType::EQUITY));

        $this->accounts->save($cashAccount);
        $this->accounts->save($bankAccount);
        $this->accounts->save($equityAccount);

        return [$cashAccount, $bankAccount, $equityAccount];
    }
}
