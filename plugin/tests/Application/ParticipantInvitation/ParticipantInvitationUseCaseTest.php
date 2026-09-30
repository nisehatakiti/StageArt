<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\CreateParticipantCommand;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\ParticipantInvitation\CancelParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CancelParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationResult;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\GetParticipantInvitationByTokenQuery;
use StageArt\Application\ParticipantInvitation\GetParticipantInvitationByTokenUseCase;
use StageArt\Application\ParticipantInvitation\ListParticipantInvitationsQuery;
use StageArt\Application\ParticipantInvitation\ListParticipantInvitationsUseCase;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationAccessDeniedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotPendingException;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\ResolveParticipantInvitationUseCase;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\FakeParticipantInvitationMailer;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantInvitationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class ParticipantInvitationUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryParticipantInvitationRepository $invitations;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryUserAccountRepository $userAccounts;
    private FakeParticipantInvitationMailer $mailer;

    private CreateParticipantUseCase $createParticipant;
    private CreateParticipantInvitationUseCase $createParticipantInvitation;
    private ResendParticipantInvitationUseCase $resendParticipantInvitation;
    private CancelParticipantInvitationUseCase $cancelParticipantInvitation;
    private GetParticipantInvitationByTokenUseCase $getParticipantInvitationByToken;
    private ResolveParticipantInvitationUseCase $resolveParticipantInvitation;
    private ListParticipantInvitationsUseCase $listParticipantInvitations;
    private ProductionAuthorizationService $productionAuthorization;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->invitations = new InMemoryParticipantInvitationRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->mailer = new FakeParticipantInvitationMailer();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );

        $this->createParticipant = new CreateParticipantUseCase(
            $this->productions,
            $this->participants,
            $this->people,
            $this->organizations,
            $this->productionAuthorization,
            new InMemoryTransactionManager()
        );

        $findPersonByEmail = new FindPersonByEmailUseCase(
            $this->emailCredentials,
            $this->userAccounts,
            new InMemoryNotificationEmailRepository(),
            $this->people
        );

        $this->resendParticipantInvitation = new ResendParticipantInvitationUseCase(
            $this->invitations,
            $this->productions,
            $this->productionAuthorization,
            $this->mailer
        );
        $this->createParticipantInvitation = new CreateParticipantInvitationUseCase(
            $this->productions,
            $this->productionAuthorization,
            $findPersonByEmail,
            $this->createParticipant,
            $this->invitations,
            $this->resendParticipantInvitation,
            $this->mailer
        );
        $this->cancelParticipantInvitation = new CancelParticipantInvitationUseCase(
            $this->invitations,
            $this->productions,
            $this->productionAuthorization
        );
        $this->getParticipantInvitationByToken = new GetParticipantInvitationByTokenUseCase($this->invitations, $this->productions);
        $this->resolveParticipantInvitation = new ResolveParticipantInvitationUseCase(
            $this->invitations,
            $this->participants,
            $this->people,
            $this->createParticipant
        );
        $this->listParticipantInvitations = new ListParticipantInvitationsUseCase(
            $this->invitations,
            $this->productions,
            $this->productionAuthorization
        );
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    private function givenParticipantManagerDelegate(Production $production, int $delegateWordPressUserId): Person
    {
        $delegatePerson = Person::create($delegateWordPressUserId);
        $this->people->save($delegatePerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        ));

        return $delegatePerson;
    }

    // --- CreateParticipantInvitationUseCase ------------------------------

    public function test_primary_manager_can_invite_an_unregistered_email(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST',
            'a remark'
        ));

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_CREATED, $result->outcome);
        $this->assertNotNull($result->invitation);
        $this->assertSame('invitee@example.com', $result->invitation->email);
        $this->assertSame('山田 花子', $result->invitation->invitedName);
        $this->assertSame('PENDING', $result->invitation->status);
        $this->assertCount(1, $this->mailer->invitationEmails);
        $this->assertSame('invitee@example.com', $this->mailer->invitationEmails[0]['to']);
        $this->assertNotEmpty($this->mailer->invitationEmails[0]['token']);
    }

    public function test_participant_manager_delegate_can_invite_an_unregistered_email(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenParticipantManagerDelegate($production, 2);

        $result = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            2,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_CREATED, $result->outcome);
    }

    public function test_a_member_without_participant_manager_role_cannot_invite(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $ordinaryMember = Person::create(3);
        $this->people->save($ordinaryMember);

        $this->expectException(ParticipantAccessDeniedException::class);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            3,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));
    }

    public function test_inviting_an_email_that_already_belongs_to_an_existing_person_adds_them_directly(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $existingPerson = Person::create(9);
        $this->people->save($existingPerson);
        $userAccount = UserAccount::create($existingPerson->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'already-registered@example.com', 'hash'));

        $result = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 太郎',
            'already-registered@example.com',
            'CAST',
            'a remark'
        ));

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_PARTICIPANT_ADDED, $result->outcome);
        $this->assertNotNull($result->participant);
        $this->assertSame($existingPerson->id()->toString(), $result->participant->subjectId);
        $this->assertSame('CAST', $result->participant->participantType);
        $this->assertSame('a remark', $result->participant->remarks);
        $this->assertCount(0, $this->mailer->invitationEmails);
        $this->assertCount(0, $this->invitations->findByProductionId($production->id()));

        // §2-A: an existing Person is looked up and linked directly - no
        // NAME_ONLY Participant is ever created for this path.
        $participant = $this->participants->findByProductionAndSubject(
            $production->id(),
            ParticipantSubjectType::person(),
            $existingPerson->id()->toString(),
            ParticipantType::cast()
        );
        $this->assertNotNull($participant);
    }

    public function test_inviting_the_same_email_and_type_twice_resends_instead_of_duplicating(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $first = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $second = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_RESENT, $second->outcome);
        $this->assertSame($first->invitation->id, $second->invitation->id);
        $this->assertCount(2, $this->mailer->invitationEmails);
        $this->assertNotSame($this->mailer->invitationEmails[0]['token'], $this->mailer->invitationEmails[1]['token']);

        // Still exactly one row for this (production, email, type) tuple.
        $matching = $this->invitations->findByProductionEmailAndType(
            $production->id(),
            'invitee@example.com',
            ParticipantType::cast()
        );
        $this->assertCount(1, $matching);
    }

    public function test_inviting_the_same_email_and_type_after_cancellation_creates_a_new_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $first = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $this->cancelParticipantInvitation->execute(new CancelParticipantInvitationCommand($first->invitation->id, 1));

        $second = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_CREATED, $second->outcome);
        $this->assertNotSame($first->invitation->id, $second->invitation->id);

        $matching = $this->invitations->findByProductionEmailAndType(
            $production->id(),
            'invitee@example.com',
            ParticipantType::cast()
        );
        $this->assertCount(2, $matching);
    }

    // --- ResendParticipantInvitationUseCase ------------------------------

    public function test_resend_by_someone_without_permission_is_denied(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $ordinaryMember = Person::create(3);
        $this->people->save($ordinaryMember);

        $this->expectException(ParticipantInvitationAccessDeniedException::class);
        $this->resendParticipantInvitation->execute(new ResendParticipantInvitationCommand($created->invitation->id, 3));
    }

    public function test_resend_of_a_consumed_invitation_is_rejected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $invitedPerson = Person::create(20);
        $this->people->save($invitedPerson);
        $this->resolveParticipantInvitation->execute($invitedPerson->id(), 'invitee@example.com');

        $this->expectException(ParticipantInvitationNotPendingException::class);
        $this->resendParticipantInvitation->execute(new ResendParticipantInvitationCommand($created->invitation->id, 1));
    }

    /** §9: resending an existing PENDING invitation reuses the same row -
     * only the token hash and expiry change, the originally submitted
     * invitedName/remarks are preserved as-is (see this round's final
     * report for the 要確認 item: ResendParticipantInvitationUseCase has
     * no name/remarks parameters, so a differing name/remarks entered on
     * a resend attempt is silently ignored rather than overwriting the
     * stored invitation - left unresolved rather than decided here). */
    public function test_resending_the_same_invitation_updates_token_and_expiry_but_preserves_the_invited_name(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $first = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST',
            'original remark'
        ));

        $second = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST',
            'original remark'
        ));

        $this->assertSame($first->invitation->id, $second->invitation->id);
        $this->assertSame('山田 花子', $second->invitation->invitedName);
        $this->assertCount(2, $this->mailer->invitationEmails);
        $this->assertNotSame($this->mailer->invitationEmails[0]['token'], $this->mailer->invitationEmails[1]['token']);
    }

    // --- CancelParticipantInvitationUseCase ------------------------------

    public function test_cancel_transitions_status_and_prevents_further_resend(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $result = $this->cancelParticipantInvitation->execute(new CancelParticipantInvitationCommand($created->invitation->id, 1));
        $this->assertSame('CANCELLED', $result->status);

        $this->expectException(ParticipantInvitationNotPendingException::class);
        $this->resendParticipantInvitation->execute(new ResendParticipantInvitationCommand($created->invitation->id, 1));
    }

    public function test_cancel_by_someone_without_permission_is_denied(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $ordinaryMember = Person::create(3);
        $this->people->save($ordinaryMember);

        $this->expectException(ParticipantInvitationAccessDeniedException::class);
        $this->cancelParticipantInvitation->execute(new CancelParticipantInvitationCommand($created->invitation->id, 3));
    }

    // --- GetParticipantInvitationByTokenUseCase --------------------------

    public function test_get_by_token_returns_a_preview_for_a_usable_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));
        $token = $this->mailer->invitationEmails[0]['token'];

        $preview = $this->getParticipantInvitationByToken->execute(new GetParticipantInvitationByTokenQuery($token));

        $this->assertSame('Show', $preview->productionName);
        $this->assertSame('invitee@example.com', $preview->email);
        $this->assertSame('山田 花子', $preview->name);
        $this->assertSame('CAST', $preview->participantType);
        $this->assertSame('PENDING', $preview->status);
    }

    public function test_get_by_token_rejects_an_unknown_token(): void
    {
        $this->expectException(ParticipantInvitationNotFoundException::class);
        $this->getParticipantInvitationByToken->execute(new GetParticipantInvitationByTokenQuery('never-issued'));
    }

    public function test_get_by_token_rejects_an_expired_token(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $primaryManager = $this->people->findByWordPressUserId(1);

        $rawToken = 'expired-raw-token';
        $invitation = ParticipantInvitation::create(
            $production->id(),
            'invitee@example.com',
            '山田 花子',
            $primaryManager->id(),
            ParticipantType::cast(),
            null,
            hash('sha256', $rawToken),
            (new DateTimeImmutable())->sub(new DateInterval('PT1H'))
        );
        $this->invitations->save($invitation);

        $this->expectException(ParticipantInvitationNotFoundException::class);
        $this->getParticipantInvitationByToken->execute(new GetParticipantInvitationByTokenQuery($rawToken));
    }

    public function test_get_by_token_rejects_a_cancelled_token(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));
        $token = $this->mailer->invitationEmails[0]['token'];
        $this->cancelParticipantInvitation->execute(new CancelParticipantInvitationCommand($created->invitation->id, 1));

        $this->expectException(ParticipantInvitationNotFoundException::class);
        $this->getParticipantInvitationByToken->execute(new GetParticipantInvitationByTokenQuery($token));
    }

    // --- ResolveParticipantInvitationUseCase (§2 absolute condition) ----

    public function test_resolve_creates_a_participant_and_consumes_the_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST',
            'remark text'
        ));

        $invitedPerson = Person::create(30);
        $this->people->save($invitedPerson);

        $this->resolveParticipantInvitation->execute($invitedPerson->id(), 'invitee@example.com');

        $participant = $this->participants->findByProductionAndSubject(
            $production->id(),
            ParticipantSubjectType::person(),
            $invitedPerson->id()->toString(),
            ParticipantType::cast()
        );
        $this->assertNotNull($participant);
        $this->assertSame('remark text', $participant->remarks());
        $this->assertSame('CAST', $participant->participantType()->toString());

        $resolved = array_values(array_filter(
            $this->invitations->findByProductionId($production->id()),
            static fn ($invitation) => $invitation->id()->toString() === $created->invitation->id
        ))[0];
        $this->assertSame('CONSUMED', $resolved->status()->toString());
        $this->assertNotNull($resolved->consumedAt());
    }

    /** §19: an unregistered invitee's Participant is never created as
     * NAME_ONLY - it is always PERSON, keyed by the Person ID they get
     * once they actually register, and only once they register. */
    public function test_an_unregistered_invitee_is_not_provisionally_registered_as_name_only(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $this->assertCount(0, $this->participants->findByProductionId($production->id()));

        $invitedPerson = Person::create(35);
        $this->people->save($invitedPerson);
        $this->resolveParticipantInvitation->execute($invitedPerson->id(), 'invitee@example.com');

        $created = $this->participants->findByProductionId($production->id());
        $this->assertCount(1, $created);
        $this->assertSame('PERSON', $created[0]->subjectType()->toString());
        $this->assertSame($invitedPerson->id()->toString(), $created[0]->subjectId());
    }

    public function test_resolve_does_not_duplicate_when_a_manager_already_added_the_same_person_directly(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $invitedPerson = Person::create(31);
        $this->people->save($invitedPerson);

        // §12: manager adds the same Person directly by Person ID while
        // the invitation is still PENDING.
        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $invitedPerson->id()->toString(),
            'CAST'
        ));
        $this->assertCount(1, $this->participants->findByProductionId($production->id()));

        // The invited Person now completes registration; resolving must
        // not create a second Participant row.
        $this->resolveParticipantInvitation->execute($invitedPerson->id(), 'invitee@example.com');

        $this->assertCount(1, $this->participants->findByProductionId($production->id()));

        $resolved = array_values(array_filter(
            $this->invitations->findByProductionId($production->id()),
            static fn ($invitation) => $invitation->id()->toString() === $created->invitation->id
        ))[0];
        $this->assertSame('CONSUMED', $resolved->status()->toString());
    }

    public function test_resolve_does_not_touch_an_expired_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $primaryManager = $this->people->findByWordPressUserId(1);

        $invitation = ParticipantInvitation::create(
            $production->id(),
            'invitee@example.com',
            '山田 花子',
            $primaryManager->id(),
            ParticipantType::cast(),
            null,
            hash('sha256', 'some-token'),
            (new DateTimeImmutable())->sub(new DateInterval('PT1H'))
        );
        $this->invitations->save($invitation);

        $invitedPerson = Person::create(32);
        $this->people->save($invitedPerson);

        $this->resolveParticipantInvitation->execute($invitedPerson->id(), 'invitee@example.com');

        $this->assertCount(0, $this->participants->findByProductionId($production->id()));
        $stillPending = $this->invitations->findById($invitation->id());
        $this->assertSame('PENDING', $stillPending->status()->toString());
        $this->assertNull($stillPending->consumedAt());
    }

    /**
     * §2's absolute condition, verified directly: if the inviting
     * manager's own PARTICIPANT_MANAGER Role has since been deactivated,
     * ResolveParticipantInvitationUseCase must NOT force the Participant
     * through anyway - it must go through CreateParticipantUseCase's own
     * canManageParticipants() check exactly as any other caller would,
     * and that check must legitimately fail here. This is the test that
     * proves Participant creation Authorization was not bypassed: if it
     * had been (e.g. by calling Participant::create() directly, or by
     * treating the invitee as a manager), this test would fail because a
     * Participant WOULD exist despite the inviter's Role no longer being
     * active.
     */
    public function test_resolve_does_not_create_a_participant_when_the_inviter_has_since_lost_participant_manager(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $delegatePerson = $this->givenParticipantManagerDelegate($production, 2);

        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            2,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        // The inviting delegate's PARTICIPANT_MANAGER Role is revoked
        // after the invitation was sent, before the invitee registers.
        $delegate = $this->delegates->findByProductionAndPersonAndRole(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager()
        );
        $delegate->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($delegate);

        $invitedPerson = Person::create(33);
        $this->people->save($invitedPerson);

        $this->resolveParticipantInvitation->execute($invitedPerson->id(), 'invitee@example.com');

        $this->assertCount(0, $this->participants->findByProductionId($production->id()));

        $stillPending = $this->invitations->findById(ParticipantInvitationId::fromString($created->invitation->id));
        $this->assertSame('PENDING', $stillPending->status()->toString());
        $this->assertNull($stillPending->consumedAt());
    }

    public function test_resolve_ignores_a_cancelled_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));
        $this->cancelParticipantInvitation->execute(new CancelParticipantInvitationCommand($created->invitation->id, 1));

        $invitedPerson = Person::create(34);
        $this->people->save($invitedPerson);

        $this->resolveParticipantInvitation->execute($invitedPerson->id(), 'invitee@example.com');

        $this->assertCount(0, $this->participants->findByProductionId($production->id()));
    }

    // --- ListParticipantInvitationsUseCase --------------------------------

    public function test_list_returns_invitations_for_the_production(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invitee@example.com',
            'CAST'
        ));

        $results = $this->listParticipantInvitations->execute(new ListParticipantInvitationsQuery($production->id()->toString(), 1));

        $this->assertCount(1, $results);
        $this->assertSame('invitee@example.com', $results[0]->email);
        $this->assertSame('山田 花子', $results[0]->invitedName);
    }

    public function test_list_by_someone_without_permission_is_denied(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $ordinaryMember = Person::create(3);
        $this->people->save($ordinaryMember);

        $this->expectException(ParticipantAccessDeniedException::class);
        $this->listParticipantInvitations->execute(new ListParticipantInvitationsQuery($production->id()->toString(), 3));
    }
}
