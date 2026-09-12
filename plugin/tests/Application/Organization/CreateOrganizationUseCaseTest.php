<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\CreateOrganizationCommand;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Organization\OrganizationSlugAlreadyTakenException;
use StageArt\Domain\Organization\OrganizationId;
use RuntimeException;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\SaveFailingMembershipRepository;

final class CreateOrganizationUseCaseTest extends TestCase
{
    private function makeUseCase(
        InMemoryOrganizationRepository $organizations,
        InMemoryPersonRepository $people,
        $memberships
    ): CreateOrganizationUseCase {
        return new CreateOrganizationUseCase(
            $organizations,
            $people,
            $memberships,
            new InMemoryAccountRepository(),
            new InMemoryJournalEntryRepository(),
            new InMemoryTransactionManager()
        );
    }

    public function test_creating_an_organization_makes_the_requester_its_owner(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();

        $useCase = $this->makeUseCase($organizations, $people, $memberships);

        $result = $useCase->execute(new CreateOrganizationCommand(42, 'New Theatre', 'new-theatre'));

        $this->assertSame('New Theatre', $result->name);
        $this->assertSame(RoleKey::OWNER, $result->currentPersonRole);

        $person = $people->findByWordPressUserId(42);
        $this->assertNotNull($person);

        $membership = $memberships->findByOrganizationAndPerson(
            OrganizationId::fromString($result->id),
            $person->id()
        );
        $this->assertNotNull($membership);
        $this->assertSame(RoleKey::OWNER, $membership->roleKey()->toString());
    }

    public function test_reuses_the_existing_person_for_the_same_wordpress_user(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();

        $useCase = $this->makeUseCase($organizations, $people, $memberships);

        $useCase->execute(new CreateOrganizationCommand(7, 'First Org', 'first-org'));
        $useCase->execute(new CreateOrganizationCommand(7, 'Second Org', 'second-org'));

        $person = $people->findByWordPressUserId(7);
        $this->assertNotNull($person);
        $this->assertCount(2, $memberships->findByPersonId($person->id()));
    }

    public function test_owner_membership_save_failure_propagates_instead_of_being_swallowed(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new SaveFailingMembershipRepository(new InMemoryMembershipRepository());

        $useCase = $this->makeUseCase($organizations, $people, $memberships);

        $this->expectException(RuntimeException::class);

        $useCase->execute(new CreateOrganizationCommand(99, 'Doomed Org', 'doomed-org'));

        // Full atomicity (that no Organization row is left behind on a real
        // database when the Owner Membership write fails) is verified against
        // WordPressTransactionManager on ConoHa, not here: InMemoryOrganizationRepository
        // has no rollback capability to prove that against.
    }

    public function test_creating_with_an_already_taken_slug_is_rejected(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();

        $useCase = $this->makeUseCase($organizations, $people, $memberships);

        $useCase->execute(new CreateOrganizationCommand(1, 'First Theatre', 'shared-slug'));

        $this->expectException(OrganizationSlugAlreadyTakenException::class);

        $useCase->execute(new CreateOrganizationCommand(2, 'Second Theatre', 'shared-slug'));
    }

    public function test_created_organization_carries_its_slug_and_is_unpublished(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();

        $useCase = $this->makeUseCase($organizations, $people, $memberships);

        $result = $useCase->execute(new CreateOrganizationCommand(1, 'New Theatre', 'new-theatre-2'));

        $this->assertSame('new-theatre-2', $result->slug);
        $this->assertNull($result->publishedAt);
    }

    public function test_accounting_disabled_by_default_and_creates_no_accounts_or_journal_entries(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $accounts = new InMemoryAccountRepository();
        $journalEntries = new InMemoryJournalEntryRepository();

        $useCase = new CreateOrganizationUseCase(
            $organizations,
            $people,
            $memberships,
            $accounts,
            $journalEntries,
            new InMemoryTransactionManager()
        );

        $result = $useCase->execute(new CreateOrganizationCommand(1, 'No Accounting Theatre', 'no-accounting'));

        $this->assertFalse($result->accountingEnabled);
        $this->assertCount(0, $accounts->findByOrganizationId(OrganizationId::fromString($result->id)));
        $this->assertCount(0, $journalEntries->all());
    }

    public function test_enabling_accounting_with_an_opening_balance_creates_accounts_and_a_posted_opening_journal_entry(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $accounts = new InMemoryAccountRepository();
        $journalEntries = new InMemoryJournalEntryRepository();

        $useCase = new CreateOrganizationUseCase(
            $organizations,
            $people,
            $memberships,
            $accounts,
            $journalEntries,
            new InMemoryTransactionManager()
        );

        $result = $useCase->execute(new CreateOrganizationCommand(
            1,
            'Accounting Theatre',
            'accounting-theatre',
            null,
            null,
            true,
            10000,
            50000
        ));

        $this->assertTrue($result->accountingEnabled);

        $organizationAccounts = $accounts->findByOrganizationId(OrganizationId::fromString($result->id));
        $this->assertCount(3, $organizationAccounts);

        $entries = $journalEntries->all();
        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertTrue($entry->status()->equals(\StageArt\Domain\JournalEntry\JournalEntryStatus::fromString(\StageArt\Domain\JournalEntry\JournalEntryStatus::POSTED)));
        $this->assertCount(3, $entry->lines());
    }

    public function test_enabling_accounting_with_no_opening_balance_still_creates_the_standard_accounts_but_no_journal_entry(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $accounts = new InMemoryAccountRepository();
        $journalEntries = new InMemoryJournalEntryRepository();

        $useCase = new CreateOrganizationUseCase(
            $organizations,
            $people,
            $memberships,
            $accounts,
            $journalEntries,
            new InMemoryTransactionManager()
        );

        $result = $useCase->execute(new CreateOrganizationCommand(
            1,
            'Zero Balance Theatre',
            'zero-balance-theatre',
            null,
            null,
            true
        ));

        $this->assertTrue($result->accountingEnabled);
        $this->assertCount(3, $accounts->findByOrganizationId(OrganizationId::fromString($result->id)));
        $this->assertCount(0, $journalEntries->all());
    }

    public function test_a_negative_opening_balance_is_rejected(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();

        $useCase = $this->makeUseCase($organizations, $people, $memberships);

        $this->expectException(\InvalidArgumentException::class);

        $useCase->execute(new CreateOrganizationCommand(1, 'Bad Theatre', 'bad-theatre', null, null, true, -1));
    }
}
