<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Authentication;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Authentication\AuthenticateWithGoogleCommand;
use StageArt\Application\Authentication\AuthenticateWithGoogleUseCase;
use StageArt\Application\Authentication\RegisterWithEmailCommand;
use StageArt\Application\Authentication\RegisterWithEmailUseCase;
use StageArt\Application\Notification\NotificationEmailSeeder;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\ResolveParticipantInvitationUseCase;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\FakeAccessTokenIssuer;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\FakeGoogleIdTokenVerifier;
use StageArt\Tests\Support\FakeParticipantInvitationMailer;
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

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->invitations = new InMemoryParticipantInvitationRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $delegates, $this->participants);

        $createParticipant = new CreateParticipantUseCase(
            $this->productions,
            $this->participants,
            $this->people,
            $this->organizations,
            $productionAuthorization,
            new InMemoryTransactionManager()
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
        $invitationMailer = new FakeParticipantInvitationMailer();
        $this->createParticipantInvitation = new CreateParticipantInvitationUseCase(
            $this->productions,
            $productionAuthorization,
            $findPersonByEmail,
            $createParticipant,
            $this->invitations,
            new ResendParticipantInvitationUseCase($this->invitations, $this->productions, $productionAuthorization, $invitationMailer),
            $invitationMailer
        );

        $emailCredentials = new InMemoryEmailCredentialRepository();
        $userAccounts = new InMemoryUserAccountRepository();

        $this->registerWithEmail = new RegisterWithEmailUseCase(
            $emailCredentials,
            $this->people,
            $userAccounts,
            new InMemoryRefreshTokenRepository(),
            new InMemoryEmailVerificationTokenRepository(),
            new FakeAccessTokenIssuer(),
            new FakeWordPressUserProvisioner(),
            new InMemoryTransactionManager(),
            new FakeAuthMailer(),
            $resolveParticipantInvitation
        );

        $this->googleVerifier = new FakeGoogleIdTokenVerifier();
        $externalIdentities = new InMemoryExternalIdentityRepository();
        $notificationEmailSeeder = new NotificationEmailSeeder(new InMemoryNotificationEmailRepository(), $userAccounts, $emailCredentials);

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
}
