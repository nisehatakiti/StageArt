<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use DateTimeImmutable;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\ApproveParticipantRequestUseCase;
use StageArt\Application\Participant\ParticipantConsentNotifier;
use StageArt\Application\Participant\ParticipantDelegateRoleReleaser;
use StageArt\Application\Participant\ParticipantEnrollmentService;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\Participant\RejectParticipantRequestUseCase;
use StageArt\Application\Participant\RequestProductionParticipationUseCase;
use StageArt\Application\Participant\WithdrawParticipationUseCase;
use StageArt\Application\ParticipantInvitation\AcceptParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\InvitationRecipientVerifier;
use StageArt\Application\ParticipantInvitation\ListMyParticipantInvitationsUseCase;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationConditions;
use StageArt\Application\ParticipantInvitation\RejectParticipantInvitationUseCase;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\DelegateAppointmentGuard;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesUseCase;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantConsentSource;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;

/**
 * R14-B stage 3: one Production (PLANNING) with its PrimaryManager
 * (WordPress user 1) and everything the participant / invitation
 * UseCases need, all in memory. The transaction manager rolls the
 * Participant, invitation, delegate, Join Key and audit stores back on a
 * failure, so a rollback is observable; LockLog records the lock order.
 */
final class ParticipantConsentFixture
{
    public InMemoryProductionRepository $productions;
    public InMemoryParticipantRepository $participants;
    public InMemoryParticipantInvitationRepository $invitations;
    public InMemoryProductionDelegateRepository $delegates;
    public InMemoryJoinKeyRepository $joinKeys;
    public InMemoryPersonRepository $people;
    public InMemoryMembershipRepository $memberships;
    public InMemoryUserAccountRepository $userAccounts;
    public CollatingEmailCredentialRepository $credentials;
    public CollatingNotificationEmailRepository $notificationEmails;
    public InMemoryOrganizationRepository $organizations;
    public InMemoryProjectRepository $projects;
    public InMemoryParticipantEventRepository $eventStore;
    public InMemoryNotificationDispatcher $dispatcher;
    public RollbackingTransactionManager $transactions;
    public LockLog $lockLog;
    public ProductionAuthorizationService $authorization;
    public ParticipantEventRecorder $events;
    public Production $production;
    public Person $primaryManager;
    private int $nextWordPressUserId = 100;

    public function __construct()
    {
        $this->productions = new InMemoryProductionRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->invitations = new InMemoryParticipantInvitationRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->joinKeys = new InMemoryJoinKeyRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->credentials = new CollatingEmailCredentialRepository();
        $this->notificationEmails = new CollatingNotificationEmailRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->eventStore = new InMemoryParticipantEventRepository();
        $this->dispatcher = new InMemoryNotificationDispatcher();
        $this->lockLog = new LockLog();
        $this->transactions = new RollbackingTransactionManager(
            $this->participants,
            $this->invitations,
            $this->delegates,
            $this->joinKeys,
            $this->eventStore
        );

        foreach ([$this->participants, $this->invitations, $this->delegates, $this->joinKeys] as $repository) {
            $repository->lockLog = $this->lockLog;
        }

        $this->authorization = new ProductionAuthorizationService(
            new OrganizationAuthorizationService($this->people, $this->memberships),
            $this->delegates,
            $this->participants
        );
        $this->events = new ParticipantEventRecorder($this->eventStore);

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);
        $project = Project::create($organization->id(), null);
        $this->projects->save($project);

        $this->primaryManager = Person::create(1);
        $this->people->save($this->primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $this->primaryManager->id()));

        $this->production = Production::create(
            $project->id(),
            new ProductionName('Autumn Play'),
            $this->primaryManager->id(),
            null,
            new ProductionSlug('autumn-play')
        );
        $this->productions->save($this->production);
    }

    /** Before the next execute(): a fresh lock log, with the Production lock appended to it too. */
    public function watchProductionLock(): void
    {
        $this->lockLog->reset();
        $this->productions->beforeLockGranted = function (): void {
            $this->lockLog->record('production');
        };
    }

    public function forceProductionStatus(string $status): void
    {
        ProductionStatusFixture::force($this->production, $status);
    }

    /**
     * A Person with a WordPress user, an ACTIVE (or other) UserAccount and a
     * login address (verified or not).
     */
    public function person(?string $loginEmail = null, bool $verified = true, string $accountStatus = 'ACTIVE'): Person
    {
        $person = Person::create($this->nextWordPressUserId++);
        $this->people->save($person);
        $account = UserAccount::create($person->id());

        if ($accountStatus === 'SUSPENDED') {
            $account->suspend();
        } elseif ($accountStatus === 'DISABLED') {
            $account->disable();
        }

        $this->userAccounts->save($account);

        if ($loginEmail !== null) {
            $credential = EmailCredential::create($account->id(), $loginEmail, 'hash');

            if ($verified) {
                $credential->markEmailVerified();
            }

            $this->credentials->save($credential);
        }

        return $person;
    }

    public function notificationAddress(Person $person, string $email, bool $verified): void
    {
        $this->notificationEmails->save(NotificationEmail::create($person->id(), $email, $verified, NotificationEmail::SOURCE_USER));
    }

    /** An ACTIVE PARTICIPANT_MANAGER delegate (with a consented ACTIVE row) - a non-PrimaryManager inviter. */
    public function participantManager(): Person
    {
        $manager = $this->person('manager' . $this->nextWordPressUserId . '@example.com');
        $this->participants->save(Participant::enrollFromInvitation(
            $this->production->id(),
            $manager->id()->toString(),
            ParticipantType::staff(),
            new DateTimeImmutable('-30 days'),
            null,
            '制作 花子'
        ));
        $this->delegates->save(ProductionDelegate::create($this->production->id(), $manager->id(), RoleKey::participantManager(), $this->primaryManager->id()));

        return $manager;
    }

    public function invitation(
        string $email,
        ?Person $inviter = null,
        string $type = ParticipantType::CAST,
        ?DateTimeImmutable $expiresAt = null,
        ?Production $production = null
    ): ParticipantInvitation {
        $invitation = ParticipantInvitation::create(
            ($production ?? $this->production)->id(),
            $email,
            '招待 太郎',
            ($inviter ?? $this->primaryManager)->id(),
            ParticipantType::fromString($type),
            'internal memo',
            hash('sha256', random_bytes(16)),
            $expiresAt ?? new DateTimeImmutable('+7 days')
        );
        $this->invitations->save($invitation);

        return $invitation;
    }

    /** A Participant row in any state (reconstituted, so DRAFT etc. are possible). */
    public function participantRow(
        Person $person,
        string $status,
        string $type = ParticipantType::CAST,
        ?string $enrollmentSource = null,
        ?DateTimeImmutable $requestedAt = null,
        bool $consented = false
    ): Participant {
        $participant = Participant::reconstitute(
            ParticipantId::generate(),
            $this->production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::fromString($type),
            ParticipantStatus::fromString($status),
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
            null,
            null,
            $enrollmentSource !== null ? ParticipantEnrollmentSource::fromString($enrollmentSource) : null,
            $consented ? ParticipantConsentSource::invitation() : null,
            $consented ? new DateTimeImmutable('2026-01-03T00:00:00+00:00') : null,
            $requestedAt
        );
        $this->participants->save($participant);

        return $participant;
    }

    public function delegate(Person $person, string $role = RoleKey::PARTICIPANT_MANAGER, bool $active = true): ProductionDelegate
    {
        $delegate = ProductionDelegate::create($this->production->id(), $person->id(), RoleKey::fromString($role), $this->primaryManager->id());

        if (! $active) {
            $delegate->deactivate($this->primaryManager->id());
        }

        $this->delegates->save($delegate);

        return $delegate;
    }

    public function verifier(): InvitationRecipientVerifier
    {
        return new InvitationRecipientVerifier(
            $this->authorization,
            $this->userAccounts,
            $this->credentials,
            $this->notificationEmails,
            new FindPersonByEmailUseCase($this->credentials, $this->userAccounts, $this->notificationEmails, $this->people)
        );
    }

    public function conditions(): ParticipantInvitationConditions
    {
        return new ParticipantInvitationConditions($this->authorization, $this->people);
    }

    public function enrollment(): ParticipantEnrollmentService
    {
        return new ParticipantEnrollmentService($this->participants);
    }

    public function releaser(): ParticipantDelegateRoleReleaser
    {
        return new ParticipantDelegateRoleReleaser($this->delegates, $this->events);
    }

    public function notifier(): ParticipantConsentNotifier
    {
        return new ParticipantConsentNotifier($this->dispatcher);
    }

    public function accept(): AcceptParticipantInvitationUseCase
    {
        return new AcceptParticipantInvitationUseCase(
            $this->invitations,
            $this->productions,
            $this->verifier(),
            $this->conditions(),
            $this->enrollment(),
            $this->releaser(),
            $this->events,
            $this->notifier(),
            $this->transactions
        );
    }

    public function reject(): RejectParticipantInvitationUseCase
    {
        return new RejectParticipantInvitationUseCase(
            $this->invitations,
            $this->productions,
            $this->verifier(),
            $this->conditions(),
            $this->events,
            $this->notifier(),
            $this->transactions
        );
    }

    public function listMine(): ListMyParticipantInvitationsUseCase
    {
        return new ListMyParticipantInvitationsUseCase(
            $this->invitations,
            $this->productions,
            $this->projects,
            $this->organizations,
            $this->participants,
            $this->verifier(),
            $this->conditions()
        );
    }

    public function withdraw(): WithdrawParticipationUseCase
    {
        return new WithdrawParticipationUseCase(
            $this->participants,
            $this->productions,
            $this->delegates,
            $this->authorization,
            $this->releaser(),
            $this->events,
            $this->notifier(),
            $this->transactions
        );
    }

    public function requestParticipation(): RequestProductionParticipationUseCase
    {
        return new RequestProductionParticipationUseCase(
            $this->productions,
            $this->joinKeys,
            $this->authorization,
            $this->enrollment(),
            $this->events,
            $this->transactions
        );
    }

    public function approveRequest(): ApproveParticipantRequestUseCase
    {
        return new ApproveParticipantRequestUseCase(
            $this->participants,
            $this->productions,
            $this->people,
            $this->authorization,
            $this->enrollment(),
            $this->releaser(),
            $this->events,
            $this->transactions
        );
    }

    public function rejectRequest(): RejectParticipantRequestUseCase
    {
        return new RejectParticipantRequestUseCase(
            $this->participants,
            $this->productions,
            $this->people,
            $this->authorization,
            $this->events,
            $this->transactions
        );
    }

    /** R14-B stage 4: delegate appointment / change / list over the same stores. */
    public function createDelegate(): CreateProductionDelegateUseCase
    {
        return new CreateProductionDelegateUseCase(
            $this->productions,
            $this->delegates,
            $this->people,
            $this->authorization,
            $this->transactions,
            new DelegateAppointmentGuard($this->participants),
            $this->events
        );
    }

    public function updateDelegate(): UpdateProductionDelegateUseCase
    {
        return new UpdateProductionDelegateUseCase(
            $this->delegates,
            $this->productions,
            $this->people,
            $this->authorization,
            $this->transactions,
            new DelegateAppointmentGuard($this->participants),
            $this->events
        );
    }

    public function listDelegates(): ListProductionDelegatesUseCase
    {
        return new ListProductionDelegatesUseCase($this->delegates, $this->productions, $this->people, $this->authorization);
    }

    /**
     * @return string[] the notification types sent so far
     */
    public function notificationTypes(): array
    {
        return array_map(static fn (array $sent): string => $sent['type'], $this->dispatcher->dispatched());
    }

    /**
     * Every recorded audit detail and every notification payload, as text,
     * to check that no address, invited_name or token leaked into them.
     */
    public function everythingRecordedAndSent(): string
    {
        $parts = [];

        foreach ($this->eventStore->all() as $event) {
            $parts[] = json_encode($event->detail());
        }

        foreach ($this->dispatcher->dispatched() as $sent) {
            $parts[] = json_encode($sent['payload'], JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $parts);
    }
}
