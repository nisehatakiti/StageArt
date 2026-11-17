<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\CreateParticipantCommand;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\ParticipantInvitation\CancelParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CancelParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationResult;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\ListParticipantInvitationsQuery;
use StageArt\Application\ParticipantInvitation\ListParticipantInvitationsUseCase;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationAccessDeniedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotPendingException;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationUseCase;
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
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantInvitationRepository;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\ParticipantInvitation\InvitationSendService;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationConditions;
use StageArt\Infrastructure\WordPress\ParticipantInvitation\WordPressConfigEmailKeyring;
use StageArt\Tests\Support\ConsentedParticipant;
use StageArt\Tests\Support\InMemoryParticipantEventRepository;
use StageArt\Tests\Support\InMemoryParticipantInvitationSendRepository;
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
            new InMemoryTransactionManager(),
            new PersonEmailResolver(
                $this->people,
                $this->userAccounts,
                $this->emailCredentials,
                new FakeWordPressUserLookup(),
                new InMemoryNotificationEmailRepository()
            )
        );

        // R14-B stage 4: invitations are counted, recorded and sent by InvitationSendService.
        $conditions = new ParticipantInvitationConditions($this->productionAuthorization, $this->people);
        $sendService = new InvitationSendService(
            new InMemoryParticipantInvitationSendRepository(),
            $this->invitations,
            new WordPressConfigEmailKeyring(['v1' => str_repeat('k', 32)], 'v1', false),
            $this->mailer,
            new ParticipantEventRecorder(new InMemoryParticipantEventRepository()),
            new InMemoryTransactionManager()
        );
        $this->resendParticipantInvitation = new ResendParticipantInvitationUseCase(
            $this->invitations,
            $this->productions,
            $this->productionAuthorization,
            $conditions,
            $sendService,
            new InMemoryTransactionManager()
        );
        $this->createParticipantInvitation = new CreateParticipantInvitationUseCase(
            $this->productions,
            $this->productionAuthorization,
            $this->invitations,
            $this->resendParticipantInvitation,
            $conditions,
            $sendService,
            new InMemoryTransactionManager()
        );
        $this->cancelParticipantInvitation = new CancelParticipantInvitationUseCase(
            $this->invitations,
            $this->productions,
            $this->productionAuthorization
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
        // R14-B stage 4: an effective delegate is a consented ACTIVE PERSON Participant (§4.6).
        ConsentedParticipant::seed($this->participants, $production->id(), $delegatePerson->id());
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

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_SENT, $result->outcome);
        $this->assertNotNull($result->invitation);
        $this->assertSame('invitee@example.com', $result->invitation->email);
        $this->assertSame('山田 花子', $result->invitation->invitedName);
        $this->assertSame('PENDING', $result->invitation->status);
        $this->assertCount(1, $this->mailer->invitationEmails);
        $this->assertSame('invitee@example.com', $this->mailer->invitationEmails[0]['to']);
        // R14-B stage 4 (U5 C案): the e-mail carries no token.
        $this->assertArrayNotHasKey('token', $this->mailer->invitationEmails[0]);
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

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_SENT, $result->outcome);
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

    /**
     * R14-B stage 4 (§7.1 / §9.2, D10): an address that belongs to an
     * existing Person is invited like any other - nobody is added directly
     * and the response does not tell that an account exists. (Before stage 4
     * this added the Person directly: OUTCOME_PARTICIPANT_ADDED.)
     */
    public function test_inviting_an_email_that_already_belongs_to_an_existing_person_invites_them_like_anyone(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $existingPerson = Person::create(9);
        $existingPerson->setName('佐藤', '一郎');
        $this->people->save($existingPerson);
        $userAccount = UserAccount::create($existingPerson->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save((static function (EmailCredential $credential): EmailCredential { $credential->markEmailVerified(); return $credential; })(EmailCredential::create($userAccount->id(), 'already-registered@example.com', 'hash'))); // R14-A: an existing owner has a verified address

        $result = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '佐藤一郎（劇団いるか）',
            'already-registered@example.com',
            'CAST',
            'a remark'
        ));

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_SENT, $result->outcome);
        $this->assertSame(['outcome', 'invitation'], array_keys($result->toArray()), 'No participant in the response');
        $this->assertSame('佐藤一郎（劇団いるか）', $result->invitation->invitedName, 'The name the manager typed, never the Person\'s');
        $text = json_encode($result->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($existingPerson->id()->toString(), $text);
        $this->assertStringNotContainsString('佐藤",', $text);
        $this->assertCount(1, $this->mailer->invitationEmails);
        $this->assertCount(1, $this->invitations->findByProductionId($production->id()));
        $this->assertNull($this->participants->findByProductionAndSubject(
            $production->id(),
            ParticipantSubjectType::person(),
            $existingPerson->id()->toString(),
            ParticipantType::cast()
        ), 'Nobody is added before accepting');
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

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_SENT, $second->outcome);
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

        // A pre-R14-B CONSUMED row (the auto-resolution that produced it was removed in stage 4).
        $consumed = $this->invitations->findById(ParticipantInvitationId::fromString($created->invitation->id));
        $consumed->consume();
        $this->invitations->save($consumed);

        $this->expectException(ParticipantInvitationNotPendingException::class);
        $this->resendParticipantInvitation->execute(new ResendParticipantInvitationCommand($created->invitation->id, 1));
    }

    /** §9: resending an existing PENDING invitation reuses the same row -
     * only the expiry changes (R14-B stage 4: the token is no longer
     * changed - the e-mail carries none), the originally submitted
     * invitedName/remarks are preserved as-is (see this round's final
     * report for the 要確認 item: ResendParticipantInvitationUseCase has
     * no name/remarks parameters, so a differing name/remarks entered on
     * a resend attempt is silently ignored rather than overwriting the
     * stored invitation - left unresolved rather than decided here). */
    public function test_resending_the_same_invitation_keeps_the_token_and_the_invited_name(): void
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
        $tokenHash = $this->invitations->findById(ParticipantInvitationId::fromString($first->invitation->id))->tokenHash();
        $this->assertNotEmpty($tokenHash);
        $this->assertSame(1, $this->invitations->findById(ParticipantInvitationId::fromString($first->invitation->id))->resendCount());
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
