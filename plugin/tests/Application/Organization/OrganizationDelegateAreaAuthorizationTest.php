<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Account\AccountAccessDeniedException;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Account\ListAccountsForOrganizationQuery;
use StageArt\Application\Account\ListAccountsUseCase;
use StageArt\Application\JournalEntry\JournalEntryAccessDeniedException;
use StageArt\Application\JournalEntry\PostJournalEntryCommand;
use StageArt\Application\JournalEntry\PostJournalEntryUseCase;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Organization\AssignOrganizationDelegateCommand;
use StageArt\Application\Organization\AssignOrganizationDelegateUseCase;
use StageArt\Application\Organization\ListOrganizationMembersQuery;
use StageArt\Application\Organization\ListOrganizationMembersUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OwnerTransferCommand;
use StageArt\Application\Organization\OwnerTransferUseCase;
use StageArt\Application\Organization\UpdateOrganizationCommand;
use StageArt\Application\Organization\UpdateOrganizationMemberRemarksCommand;
use StageArt\Application\Organization\UpdateOrganizationMemberRemarksUseCase;
use StageArt\Application\Organization\UpdateOrganizationUseCase;
use StageArt\Application\OrganizationInvitation\CreateOrganizationInvitationCommand;
use StageArt\Application\OrganizationInvitation\CreateOrganizationInvitationUseCase;
use StageArt\Application\OrganizationInvitation\ListOrganizationInvitationsQuery;
use StageArt\Application\OrganizationInvitation\ListOrganizationInvitationsUseCase;
use StageArt\Application\OrganizationInvitation\ResendOrganizationInvitationUseCase;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Production\CreateProductionCommand;
use StageArt\Application\Production\CreateProductionUseCase;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Project\CreateProjectCommand;
use StageArt\Application\Project\CreateProjectUseCase;
use StageArt\Application\Project\ProjectAccessDeniedException;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\FakeOrganizationInvitationMailer;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryOrganizationInvitationRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;
use Throwable;

/**
 * 代理人の管理領域 × 実際の管理操作: each area opens exactly its own
 * operations to a delegate, a delegate without it is refused, and the
 * Owner-only operations stay Owner-only whatever areas a delegate holds.
 *
 * WordPress user ids: 1 Owner, 2 delegate under test, 3 plain member,
 * 9 outsider (Owner of another Organization).
 */
final class OrganizationDelegateAreaAuthorizationTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationRepository $organizations;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryProjectRepository $projects;
    private InMemoryProductionRepository $productions;
    private InMemoryJournalEntryRepository $journalEntries;
    private OrganizationAuthorizationService $authorization;
    private Organization $organization;
    private Project $project;
    private Person $owner;
    private Person $delegate;
    private Person $member;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->journalEntries = new InMemoryJournalEntryRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($this->organization);
        $this->project = Project::create($this->organization->id(), 'Bridge');
        $this->projects->save($this->project);

        $this->owner = $this->givenPerson(1, RoleKey::owner());
        $this->delegate = $this->givenPerson(2, RoleKey::organizationManager());
        $this->member = $this->givenPerson(3, RoleKey::member());

        $elsewhere = Organization::create(new OrganizationName('Other Co'));
        $this->organizations->save($elsewhere);
        $outsider = Person::create(9);
        $this->people->save($outsider);
        $this->userAccounts->save(UserAccount::create($outsider->id()));
        $this->memberships->save(Membership::createOwnerMembership($elsewhere->id(), $outsider->id()));
    }

    private function givenPerson(int $wordPressUserId, RoleKey $role): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->userAccounts->save(UserAccount::create($person->id()));
        $this->memberships->save(Membership::create($this->organization->id(), $person->id(), $role));

        return $person;
    }

    /** @param string[] $areas */
    private function delegateHolds(array $areas): void
    {
        $membership = $this->memberships->findByOrganizationAndPerson($this->organization->id(), $this->delegate->id());
        $membership->replaceDelegateAreas($areas);
        $this->memberships->save($membership);
    }

    /** @return string[] every area except the given one */
    private function allBut(string $area): array
    {
        return array_values(array_diff(DelegateArea::all(), [$area]));
    }

    private function assertAllowed(callable $operation): void
    {
        $operation();
        $this->addToAssertionCount(1);
    }

    /** @param class-string<Throwable> $exception */
    private function assertRefused(string $exception, callable $operation, string $who): void
    {
        try {
            $operation();
        } catch (Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown, "{$who}: unexpected " . get_class($thrown) . ': ' . $thrown->getMessage());

            return;
        }

        $this->fail("{$who} must be refused.");
    }

    // ---- メンバー管理 ----------------------------------------------------

    /** @return array<string, callable(int): mixed> */
    private function memberOperations(): array
    {
        $personEmailResolver = new PersonEmailResolver(
            $this->people,
            $this->userAccounts,
            new InMemoryEmailCredentialRepository(),
            new FakeWordPressUserLookup(),
            new InMemoryNotificationEmailRepository()
        );
        $listMembers = new ListOrganizationMembersUseCase($this->memberships, $this->people, $this->authorization, $personEmailResolver);
        $updateRemarks = new UpdateOrganizationMemberRemarksUseCase($this->memberships, $this->authorization);
        $invitations = new InMemoryOrganizationInvitationRepository();
        $mailer = new FakeOrganizationInvitationMailer();
        $createInvitation = new CreateOrganizationInvitationUseCase(
            $this->organizations,
            $this->authorization,
            new FindPersonByEmailUseCase(new InMemoryEmailCredentialRepository(), $this->userAccounts, new InMemoryNotificationEmailRepository(), $this->people),
            $this->memberships,
            $invitations,
            new ResendOrganizationInvitationUseCase($invitations, $this->organizations, $this->authorization, $mailer),
            $mailer
        );
        $listInvitations = new ListOrganizationInvitationsUseCase($invitations, $this->organizations, $this->authorization);
        $organizationId = $this->organization->id()->toString();
        $memberPersonId = $this->member->id()->toString();

        return [
            'list members' => fn (int $user) => $listMembers->execute(new ListOrganizationMembersQuery($organizationId, $user)),
            'update 備考' => fn (int $user) => $updateRemarks->execute(new UpdateOrganizationMemberRemarksCommand($organizationId, $memberPersonId, $user, 'note')),
            'invite' => fn (int $user) => $createInvitation->execute(new CreateOrganizationInvitationCommand($organizationId, $user, 'New Person', "new{$user}@example.com")),
            'list invitations' => fn (int $user) => $listInvitations->execute(new ListOrganizationInvitationsQuery($organizationId, $user)),
        ];
    }

    public function test_members_area_opens_the_member_management_operations(): void
    {
        $this->delegateHolds([DelegateArea::MEMBERS]);

        foreach ($this->memberOperations() as $operation) {
            $this->assertAllowed(fn () => $operation(2));
            $this->assertAllowed(fn () => $operation(1));
        }
    }

    public function test_without_the_members_area_member_management_is_refused(): void
    {
        $this->delegateHolds($this->allBut(DelegateArea::MEMBERS));

        foreach ($this->memberOperations() as $name => $operation) {
            $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $operation(2), "delegate without MEMBERS: {$name}");
            $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $operation(3), "plain member: {$name}");
            $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $operation(9), "outsider: {$name}");
        }
    }

    // ---- 公演管理 --------------------------------------------------------

    private function createProduction(int $user, Person $primaryManager, string $slug): void
    {
        $useCase = new CreateProductionUseCase(
            $this->productions,
            $this->projects,
            $this->memberships,
            $this->userAccounts,
            $this->authorization,
            new InMemoryTransactionManager()
        );
        $useCase->execute(new CreateProductionCommand($user, $this->project->id()->toString(), 'Autumn Play', $slug, $primaryManager->id()->toString()));
    }

    public function test_productions_area_opens_creating_a_production(): void
    {
        $this->delegateHolds([DelegateArea::PRODUCTIONS]);

        $this->assertAllowed(fn () => $this->createProduction(2, $this->delegate, 'by-delegate'));
        $this->assertAllowed(fn () => $this->createProduction(1, $this->owner, 'by-owner'));
    }

    public function test_without_the_productions_area_creating_a_production_is_refused(): void
    {
        $this->delegateHolds($this->allBut(DelegateArea::PRODUCTIONS));

        $this->assertRefused(ProductionAccessDeniedException::class, fn () => $this->createProduction(2, $this->delegate, 'refused-delegate'), 'delegate without PRODUCTIONS');
        $this->assertRefused(ProductionAccessDeniedException::class, fn () => $this->createProduction(3, $this->member, 'refused-member'), 'plain member');
        $this->assertRefused(ProductionAccessDeniedException::class, fn () => $this->createProduction(9, $this->owner, 'refused-outsider'), 'outsider');
    }

    public function test_project_creation_stays_owner_only_even_with_the_productions_area(): void
    {
        $this->delegateHolds(DelegateArea::all());
        $createProject = new CreateProjectUseCase($this->projects, $this->authorization);

        $this->assertRefused(
            ProjectAccessDeniedException::class,
            fn () => $createProject->execute(new CreateProjectCommand($this->organization->id()->toString(), 2)),
            'delegate'
        );
        $this->assertAllowed(fn () => $createProject->execute(new CreateProjectCommand($this->organization->id()->toString(), 1)));
    }

    // ---- 会計管理 --------------------------------------------------------

    /** @return array{0: CreateAccountUseCase, 1: ListAccountsUseCase, 2: PostJournalEntryUseCase} */
    private function accountingUseCases(): array
    {
        $productionAuthorization = new ProductionAuthorizationService($this->authorization, new InMemoryProductionDelegateRepository(), new InMemoryParticipantRepository());
        $authorizationContract = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $identity = new CoreIdentityAdapter($this->people);
        $organizationContext = new CoreOrganizationContextAdapter($this->organizations);
        $accounts = new InMemoryAccountRepository();
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver($this->projects));

        return [
            new CreateAccountUseCase($accounts, $organizationContext, $identity, $authorizationContract),
            new ListAccountsUseCase($accounts, $organizationContext, $identity, $authorizationContract),
            new PostJournalEntryUseCase($this->journalEntries, $productionContext, $identity, $authorizationContract, new InMemoryTransactionManager()),
        ];
    }

    /** A DRAFT JournalEntry of the Organization itself (no Production). */
    private function draftOrganizationJournalEntry(): string
    {
        $entry = JournalEntry::create(
            $this->organization->id(),
            null,
            new DateTimeImmutable('2026-10-01'),
            '団体会計',
            [
                JournalEntryLine::create(AccountId::generate(), DebitCredit::debit(), 1000),
                JournalEntryLine::create(AccountId::generate(), DebitCredit::credit(), 1000),
            ],
            $this->owner->id()
        );
        $this->journalEntries->save($entry);

        return $entry->id()->toString();
    }

    public function test_accounting_area_opens_the_organization_accounting_operations(): void
    {
        $this->delegateHolds([DelegateArea::ACCOUNTING]);
        [$createAccount, $listAccounts, $postJournalEntry] = $this->accountingUseCases();
        $organizationId = $this->organization->id()->toString();

        $this->assertAllowed(fn () => $createAccount->execute(new CreateAccountCommand(2, $organizationId, '現金', AccountType::ASSET)));
        $this->assertCount(1, $listAccounts->execute(new ListAccountsForOrganizationQuery($organizationId, 2)));
        $this->assertAllowed(fn () => $postJournalEntry->execute(new PostJournalEntryCommand($this->draftOrganizationJournalEntry(), 2)));

        $this->assertAllowed(fn () => $createAccount->execute(new CreateAccountCommand(1, $organizationId, '預金', AccountType::ASSET)));
        $this->assertAllowed(fn () => $postJournalEntry->execute(new PostJournalEntryCommand($this->draftOrganizationJournalEntry(), 1)));
    }

    public function test_without_the_accounting_area_organization_accounting_is_refused(): void
    {
        $this->delegateHolds($this->allBut(DelegateArea::ACCOUNTING));
        [$createAccount, , $postJournalEntry] = $this->accountingUseCases();
        $organizationId = $this->organization->id()->toString();

        foreach ([2 => 'delegate without ACCOUNTING', 3 => 'plain member', 9 => 'outsider'] as $user => $who) {
            $this->assertRefused(
                AccountAccessDeniedException::class,
                fn () => $createAccount->execute(new CreateAccountCommand($user, $organizationId, '現金', AccountType::ASSET)),
                "{$who}: create account"
            );
            $this->assertRefused(
                JournalEntryAccessDeniedException::class,
                fn () => $postJournalEntry->execute(new PostJournalEntryCommand($this->draftOrganizationJournalEntry(), $user)),
                "{$who}: post journal entry"
            );
        }
    }

    public function test_account_list_keeps_its_member_rule_and_adds_accounting_delegates_only(): void
    {
        [, $listAccounts] = $this->accountingUseCases();
        $organizationId = $this->organization->id()->toString();

        // Unchanged: any plain member may list; an outsider may not.
        $this->assertAllowed(fn () => $listAccounts->execute(new ListAccountsForOrganizationQuery($organizationId, 3)));
        $this->assertRefused(AccountAccessDeniedException::class, fn () => $listAccounts->execute(new ListAccountsForOrganizationQuery($organizationId, 9)), 'outsider');

        // A delegate without 会計管理 is still refused, as before this change.
        $this->delegateHolds($this->allBut(DelegateArea::ACCOUNTING));
        $this->assertRefused(AccountAccessDeniedException::class, fn () => $listAccounts->execute(new ListAccountsForOrganizationQuery($organizationId, 2)), 'delegate without ACCOUNTING');
    }

    // ---- オーナーのみのまま ------------------------------------------------

    public function test_owner_only_operations_stay_owner_only_for_a_delegate_holding_every_area(): void
    {
        $this->delegateHolds(DelegateArea::all());
        $organizationId = $this->organization->id()->toString();
        $transfer = new OwnerTransferUseCase($this->memberships, $this->userAccounts, $this->authorization, new InMemoryTransactionManager());
        $assign = new AssignOrganizationDelegateUseCase($this->memberships, $this->authorization);

        // 参加コード and 参加申請 moved from Owner-only to メンバー管理; see
        // JoinKeyAndMembershipRequestAuthorizationTest.
        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $transfer->execute(new OwnerTransferCommand($organizationId, 2, $this->delegate->id()->toString())), '代表者交代');
        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $assign->execute(new AssignOrganizationDelegateCommand($organizationId, 2, $this->member->id()->toString())), '代理人を設定');

        $this->assertAllowed(fn () => $transfer->execute(new OwnerTransferCommand($organizationId, 1, $this->delegate->id()->toString())));
    }

    // ---- 団体情報 (Role itself, not an area) -------------------------------

    public function test_organization_info_stays_open_to_any_delegate_and_closed_to_a_plain_member(): void
    {
        $this->delegateHolds([]);
        $update = new UpdateOrganizationUseCase($this->organizations, $this->authorization);
        $command = fn (int $user) => new UpdateOrganizationCommand($this->organization->id()->toString(), $user, 'Renamed Co', null, null, 'ACTIVE');

        $this->assertAllowed(fn () => $update->execute($command(2)));
        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $update->execute($command(3)), 'plain member');
        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $update->execute($command(9)), 'outsider');
    }
}
