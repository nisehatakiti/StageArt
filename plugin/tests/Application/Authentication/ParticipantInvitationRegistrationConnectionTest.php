<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Authentication;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Authentication\AuthenticateWithGoogleCommand;
use StageArt\Application\Authentication\AuthenticateWithGoogleUseCase;
use StageArt\Application\Authentication\RegisterWithEmailCommand;
use StageArt\Application\Authentication\RegisterWithEmailUseCase;
use StageArt\Application\Notification\NotificationEmailSeeder;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
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
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\FakeAccessTokenIssuer;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\FakeGoogleIdTokenVerifier;
use StageArt\Tests\Support\FakeParticipantInvitationMailer;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\FakeWordPressUserProvisioner;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryEmailVerificationTokenRepository;
use StageArt\Tests\Support\InMemoryExternalIdentityRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantInvitationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryRefreshTokenRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * §14/§25: end-to-end coverage of "a self-registration completes ->
 * ResolveParticipantInvitationUseCase runs -> a matching PENDING
 * ParticipantInvitation becomes a real Participant" through the actual
 * RegisterWithEmailUseCase/AuthenticateWithGoogleUseCase entry points -
 * not just ResolveParticipantInvitationUseCase in isolation (already
 * covered by ParticipantInvitationUseCaseTest).
 */
final class ParticipantInvitationRegistrationConnectionTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryOrganizationRepository $organizations;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryParticipantRepository $participants;
    private InMemoryParticipantInvitationRepository $invitations;
    private RegisterWithEmailUseCase $registerWithEmail;
    private AuthenticateWithGoogleUseCase $authenticateWithGoogle;
    private FakeGoogleIdTokenVerifier $googleVerifier;
    private CreateParticipantInvitationUseCase $createParticipantInvitation;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private FakeParticipantInvitationMailer $invitationMailer;
    private FakeAuthMailer $authMailer;
    private InMemoryProductionDelegateRepository $delegates;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->invitations = new InMemoryParticipantInvitationRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $this->delegates, $this->participants);

        $createParticipant = new CreateParticipantUseCase(
            $this->productions,
            $this->participants,
            $this->people,
            $this->organizations,
            $productionAuthorization,
            new InMemoryTransactionManager(),
            new PersonEmailResolver(
                $this->people,
                new InMemoryUserAccountRepository(),
                new InMemoryEmailCredentialRepository(),
                new FakeWordPressUserLookup(),
                new InMemoryNotificationEmailRepository()
            )
        );
        $resolveParticipantInvitation = new ResolveParticipantInvitationUseCase(
            $this->invitations,
            $this->participants,
            $this->people,
            $createParticipant
        );

        $findPersonByEmail = new FindPersonByEmailUseCase(
            new InMemoryEmailCredentialRepository(),
            new InMemoryUserAccountRepository(),
            new InMemoryNotificationEmailRepository(),
            $this->people
        );
        $this->invitationMailer = new FakeParticipantInvitationMailer();
        $this->createParticipantInvitation = new CreateParticipantInvitationUseCase(
            $this->productions,
            $productionAuthorization,
            $findPersonByEmail,
            $createParticipant,
            $this->invitations,
            new ResendParticipantInvitationUseCase($this->invitations, $this->productions, $productionAuthorization, $this->invitationMailer),
            $this->invitationMailer
        );

        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $userAccounts = new InMemoryUserAccountRepository();
        $this->authMailer = new FakeAuthMailer();

        $this->registerWithEmail = new RegisterWithEmailUseCase(
            $this->emailCredentials,
            $this->people,
            $userAccounts,
            new InMemoryRefreshTokenRepository(),
            new InMemoryEmailVerificationTokenRepository(),
            new FakeAccessTokenIssuer(),
            new FakeWordPressUserProvisioner(),
            new InMemoryTransactionManager(),
            $this->authMailer,
            $resolveParticipantInvitation,
            $this->invitations
        );

        $this->googleVerifier = new FakeGoogleIdTokenVerifier();
        $externalIdentities = new InMemoryExternalIdentityRepository();
        $notificationEmailSeeder = new NotificationEmailSeeder(new InMemoryNotificationEmailRepository(), $userAccounts, $this->emailCredentials);

        $this->authenticateWithGoogle = new AuthenticateWithGoogleUseCase(
            $this->googleVerifier,
            $externalIdentities,
            $userAccounts,
            $this->people,
            new InMemoryRefreshTokenRepository(),
            new FakeAccessTokenIssuer(),
            new FakeWordPressUserProvisioner(),
            new InMemoryTransactionManager(),
            $notificationEmailSeeder,
            $resolveParticipantInvitation
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

    public function test_completing_email_registration_resolves_a_matching_pending_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'newmember@example.com',
            'CAST'
        ));

        $result = $this->registerWithEmail->execute(new RegisterWithEmailCommand('newmember@example.com', 'password123'));

        $participant = $this->participants->findByProductionAndSubject(
            $production->id(),
            ParticipantSubjectType::person(),
            $result->personId,
            ParticipantType::cast()
        );
        $this->assertNotNull($participant);
        // StageArt Production側氏名の権威付けラウンド AC-04: the invitation's
        // invitedName reaches the real Participant end-to-end through the
        // actual registration entry point, not just in isolation.
        $this->assertSame('山田 花子', $participant->displayName());
    }

    public function test_email_registration_with_no_matching_invitation_still_succeeds_normally(): void
    {
        $result = $this->registerWithEmail->execute(new RegisterWithEmailCommand('nobodyinvited@example.com', 'password123'));

        $this->assertTrue($result->isNewUser);
        $this->assertNotEmpty($result->accessToken);
    }

    public function test_completing_google_registration_with_a_verified_email_resolves_a_matching_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'googleuser@example.com',
            'STAFF'
        ));

        $this->googleVerifier->registerValidToken('good-token', 'google-sub-1', 'googleuser@example.com', null, null, true);
        $result = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('good-token'));

        $participant = $this->participants->findByProductionAndSubject(
            $production->id(),
            ParticipantSubjectType::person(),
            $result->personId,
            ParticipantType::staff()
        );
        $this->assertNotNull($participant);
    }

    /** §14: emailVerified=false must never auto-link. */
    public function test_google_registration_with_an_unverified_email_does_not_resolve_the_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'unverified@example.com',
            'CAST'
        ));

        $this->googleVerifier->registerValidToken('unverified-token', 'google-sub-2', 'unverified@example.com', null, null, false);
        $result = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('unverified-token'));

        $this->assertCount(0, $this->participants->findByProductionId($production->id()));

        $invitations = $this->invitations->findByEmail('unverified@example.com');
        $this->assertSame('PENDING', $invitations[0]->status()->toString());
    }

    /** §14: a different Google email than the invited address must never
     * be treated as "close enough" and auto-linked. */
    public function test_google_registration_with_a_different_email_than_the_invitation_does_not_resolve_it(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '山田 花子',
            'invited@example.com',
            'CAST'
        ));

        $this->googleVerifier->registerValidToken('different-email-token', 'google-sub-3', 'different@example.com', null, null, true);
        $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('different-email-token'));

        $this->assertCount(0, $this->participants->findByProductionId($production->id()));

        $invitations = $this->invitations->findByEmail('invited@example.com');
        $this->assertSame('PENDING', $invitations[0]->status()->toString());
    }

    public function test_google_registration_with_no_email_at_all_does_not_error_and_registers_normally(): void
    {
        $this->googleVerifier->registerValidToken('no-email-token', 'google-sub-4', null, null, null, false);

        $result = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('no-email-token'));

        $this->assertTrue($result->isNewUser);
        $this->assertNotEmpty($result->accessToken);
    }

    // --- StageArt 招待登録のメール確認省略ラウンド --------------------------

    public function test_normal_self_registration_still_requires_email_verification(): void
    {
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('selfsignup@example.com', 'password123'));

        $credential = $this->emailCredentials->findByEmail('selfsignup@example.com');
        $this->assertNotNull($credential);
        $this->assertFalse($credential->isEmailVerified());
        $this->assertCount(1, $this->authMailer->verificationEmails);
        $this->assertSame('selfsignup@example.com', $this->authMailer->verificationEmails[0]['to']);
    }

    public function test_registering_via_a_valid_invitation_token_skips_email_verification(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '佐藤 一郎',
            'invited-register@example.com',
            'CAST'
        ));
        $token = $this->invitationMailer->invitationEmails[0]['token'];

        $this->registerWithEmail->execute(new RegisterWithEmailCommand('invited-register@example.com', 'password123', $token));

        $credential = $this->emailCredentials->findByEmail('invited-register@example.com');
        $this->assertNotNull($credential);
        $this->assertTrue($credential->isEmailVerified());
        $this->assertCount(0, $this->authMailer->verificationEmails);
    }

    /** §7: "招待に記録されているメールアドレスを登録メールアドレスとして使用
     * する" - whatever the client submits as `email`, the invitation's own
     * recorded address is authoritative for this path. Proves the server
     * never trusts a client-supplied email change here, independent of
     * whatever the UI itself does or does not allow editing. */
    public function test_registering_via_invitation_token_ignores_a_different_submitted_email(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '佐藤 一郎',
            'invited-fixed@example.com',
            'CAST'
        ));
        $token = $this->invitationMailer->invitationEmails[0]['token'];

        $this->registerWithEmail->execute(new RegisterWithEmailCommand('attacker@example.com', 'password123', $token));

        $this->assertNull($this->emailCredentials->findByEmail('attacker@example.com'));
        $credential = $this->emailCredentials->findByEmail('invited-fixed@example.com');
        $this->assertNotNull($credential);
        $this->assertTrue($credential->isEmailVerified());
    }

    public function test_registering_with_an_expired_invitation_token_is_rejected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $primaryManager = $this->people->findByWordPressUserId(1);

        $rawToken = 'expired-registration-token';
        $invitation = ParticipantInvitation::create(
            $production->id(),
            'expired-invite@example.com',
            '佐藤 一郎',
            $primaryManager->id(),
            ParticipantType::cast(),
            null,
            hash('sha256', $rawToken),
            (new DateTimeImmutable())->sub(new DateInterval('PT1H'))
        );
        $this->invitations->save($invitation);

        $this->expectException(ParticipantInvitationNotFoundException::class);
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('expired-invite@example.com', 'password123', $rawToken));
    }

    public function test_registering_with_a_cancelled_invitation_token_is_rejected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '佐藤 一郎',
            'cancelled-invite@example.com',
            'CAST'
        ));
        $token = $this->invitationMailer->invitationEmails[0]['token'];

        $invitation = $this->invitations->findByEmail('cancelled-invite@example.com')[0];
        $invitation->cancel();
        $this->invitations->save($invitation);

        $this->expectException(ParticipantInvitationNotFoundException::class);
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('cancelled-invite@example.com', 'password123', $token));
    }

    public function test_registering_with_an_unknown_invitation_token_is_rejected(): void
    {
        $this->expectException(ParticipantInvitationNotFoundException::class);
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('nobody@example.com', 'password123', 'never-issued-token'));
    }

    public function test_registering_via_a_valid_invitation_token_consumes_the_invitation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '佐藤 一郎',
            'consume-check@example.com',
            'CAST'
        ));
        $token = $this->invitationMailer->invitationEmails[0]['token'];

        $this->registerWithEmail->execute(new RegisterWithEmailCommand('consume-check@example.com', 'password123', $token));

        $resolved = array_values(array_filter(
            $this->invitations->findByProductionId($production->id()),
            static fn ($invitation) => $invitation->id()->toString() === $created->invitation->id
        ))[0];
        $this->assertSame('CONSUMED', $resolved->status()->toString());
    }

    public function test_registering_via_a_valid_invitation_token_links_to_the_production_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            1,
            '佐藤 一郎',
            'link-check@example.com',
            'STAFF',
            'via invitation link'
        ));
        $token = $this->invitationMailer->invitationEmails[0]['token'];

        $result = $this->registerWithEmail->execute(new RegisterWithEmailCommand('link-check@example.com', 'password123', $token));

        $participant = $this->participants->findByProductionAndSubject(
            $production->id(),
            ParticipantSubjectType::person(),
            $result->personId,
            ParticipantType::staff()
        );
        $this->assertNotNull($participant);
        $this->assertSame('via invitation link', $participant->remarks());
    }

    /**
     * This round's absolute condition, mirrored from
     * ParticipantInvitationUseCaseTest's own equivalent: registering via
     * an invitation token must go through the exact same
     * CreateParticipantUseCase/canManageParticipants() authorization
     * ResolveParticipantInvitationUseCase always used - if the inviting
     * delegate's PARTICIPANT_MANAGER Role was revoked after the
     * invitation was sent, this registration must NOT force the
     * Participant through anyway.
     */
    public function test_registering_via_invitation_token_does_not_bypass_participant_creation_authorization(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $delegatePerson = $this->givenParticipantManagerDelegate($production, 2);

        $this->createParticipantInvitation->execute(new CreateParticipantInvitationCommand(
            $production->id()->toString(),
            2,
            '佐藤 一郎',
            'authz-check@example.com',
            'CAST'
        ));
        $token = $this->invitationMailer->invitationEmails[0]['token'];

        $delegate = $this->delegates->findByProductionAndPersonAndRole(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager()
        );
        $delegate->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($delegate);

        $this->registerWithEmail->execute(new RegisterWithEmailCommand('authz-check@example.com', 'password123', $token));

        $this->assertCount(0, $this->participants->findByProductionId($production->id()));

        $invitations = $this->invitations->findByEmail('authz-check@example.com');
        $this->assertSame('PENDING', $invitations[0]->status()->toString());
    }
}
