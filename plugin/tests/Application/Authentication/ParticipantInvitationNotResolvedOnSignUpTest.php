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
use StageArt\Application\Authentication\VerifyEmailCommand;
use StageArt\Application\Authentication\VerifyEmailUseCase;
use StageArt\Application\Notification\NotificationEmailSeeder;
use StageArt\Application\OrganizationInvitation\ResolveOrganizationInvitationUseCase;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Tests\Support\FakeAccessTokenIssuer;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\FakeGoogleIdTokenVerifier;
use StageArt\Tests\Support\FakeWordPressUserProvisioner;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryEmailVerificationTokenRepository;
use StageArt\Tests\Support\InMemoryExternalIdentityRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryOrganizationInvitationRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantInvitationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryRefreshTokenRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * PR #7 R14-B stage 4 (design doc §4.2 / §7.1 / §9.2, U5 C案): registering
 * with e-mail, verifying the address and signing in with Google never make
 * anyone a Production participant, and a Production invitation is never
 * looked up by token. `invitation_token` of a Production invitation is
 * ignored: the registration is exactly an ordinary one. Organization
 * invitations (token registration, resolution on verification / Google)
 * are unchanged (D1).
 */
final class ParticipantInvitationNotResolvedOnSignUpTest extends TestCase
{
    private const EMAIL = 'newmember@example.com';

    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryParticipantRepository $participants;
    private InMemoryParticipantInvitationRepository $invitations;
    private InMemoryOrganizationInvitationRepository $organizationInvitations;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private FakeAuthMailer $authMailer;
    private FakeGoogleIdTokenVerifier $googleVerifier;
    private RegisterWithEmailUseCase $register;
    private VerifyEmailUseCase $verify;
    private AuthenticateWithGoogleUseCase $google;
    private Organization $organization;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->invitations = new InMemoryParticipantInvitationRepository();
        $this->organizationInvitations = new InMemoryOrganizationInvitationRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->authMailer = new FakeAuthMailer();
        $this->googleVerifier = new FakeGoogleIdTokenVerifier();
        $userAccounts = new InMemoryUserAccountRepository();
        $verificationTokens = new InMemoryEmailVerificationTokenRepository();
        $resolveOrganization = new ResolveOrganizationInvitationUseCase($this->organizationInvitations, $this->memberships);

        $this->register = new RegisterWithEmailUseCase(
            $this->emailCredentials,
            $this->people,
            $userAccounts,
            new InMemoryRefreshTokenRepository(),
            $verificationTokens,
            new FakeAccessTokenIssuer(),
            new FakeWordPressUserProvisioner(),
            new InMemoryTransactionManager(),
            $this->authMailer,
            $resolveOrganization,
            $this->organizationInvitations
        );
        $this->verify = new VerifyEmailUseCase($verificationTokens, $this->emailCredentials, new InMemoryTransactionManager(), $userAccounts, $resolveOrganization);
        $this->google = new AuthenticateWithGoogleUseCase(
            $this->googleVerifier,
            new InMemoryExternalIdentityRepository(),
            $userAccounts,
            $this->people,
            new InMemoryRefreshTokenRepository(),
            new FakeAccessTokenIssuer(),
            new FakeWordPressUserProvisioner(),
            new InMemoryTransactionManager(),
            new NotificationEmailSeeder(new InMemoryNotificationEmailRepository(), $userAccounts, $this->emailCredentials),
            $resolveOrganization
        );

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));
        (new InMemoryOrganizationRepository())->save($this->organization);
    }

    /** A PENDING Production invitation to $email whose (old-style) token is $token. */
    private function participantInvitation(string $email, string $token = 'participant-token'): ParticipantInvitation
    {
        $inviter = Person::create(900);
        $this->people->save($inviter);
        $invitation = ParticipantInvitation::create(
            ProductionId::generate(),
            $email,
            '山田 花子',
            $inviter->id(),
            ParticipantType::cast(),
            null,
            hash('sha256', $token),
            new DateTimeImmutable('+7 days')
        );
        $this->invitations->save($invitation);

        return $invitation;
    }

    private function organizationInvitation(string $email, string $token = 'org-token'): OrganizationInvitation
    {
        $inviter = Person::create(901);
        $this->people->save($inviter);
        $invitation = OrganizationInvitation::create(
            $this->organization->id(),
            $email,
            '佐藤 一郎',
            $inviter->id(),
            null,
            hash('sha256', $token),
            (new DateTimeImmutable())->add(new DateInterval('P7D'))
        );
        $this->organizationInvitations->save($invitation);

        return $invitation;
    }

    private function assertUntouched(ParticipantInvitation $invitation): void
    {
        $stored = $this->invitations->findById($invitation->id());
        $this->assertTrue($stored->isPending(), 'Still PENDING - only the invitee\'s explicit acceptance resolves it');
        $this->assertNull($stored->consumedAt());
        $this->assertNull($stored->inviteePersonId());
        $this->assertSame(0, $this->invitations->saves, 'Never written by sign-up');
        $this->assertSame([], $this->participants->findByProductionId($invitation->productionId()), 'No Participant row');
    }

    public function test_email_registration_and_verification_do_not_accept_a_production_invitation(): void
    {
        $invitation = $this->participantInvitation(self::EMAIL);
        $this->invitations->saves = 0;
        $organizationInvitation = $this->organizationInvitation(self::EMAIL);

        $result = $this->register->execute(new RegisterWithEmailCommand(self::EMAIL, 'password123'));
        $this->assertUntouched($invitation);

        $this->verify->execute(new VerifyEmailCommand($this->authMailer->verificationEmails[0]['token']));

        $this->assertUntouched($invitation);
        $this->assertNotNull(
            $this->memberships->findByOrganizationAndPerson($this->organization->id(), PersonId::fromString($result->personId)),
            'The organization invitation is still resolved on verification (D1)'
        );
        $this->assertFalse($this->organizationInvitations->findById($organizationInvitation->id())->isPending());
    }

    public function test_google_sign_in_does_not_accept_a_production_invitation(): void
    {
        $invitation = $this->participantInvitation('googleuser@example.com');
        $this->invitations->saves = 0;
        $this->organizationInvitation('googleuser@example.com');
        $this->googleVerifier->registerValidToken('good-token', 'google-sub-1', 'googleuser@example.com', null, null, true);

        $result = $this->google->execute(new AuthenticateWithGoogleCommand('good-token'));

        $this->assertUntouched($invitation);
        $this->assertNotNull(
            $this->memberships->findByOrganizationAndPerson($this->organization->id(), PersonId::fromString($result->personId)),
            'The organization invitation is still resolved on Google sign-in (D1)'
        );
    }

    /** §7.1: a Production invitation token is ignored - the result is that of a registration without a token. */
    public function test_a_production_invitation_token_is_ignored_at_registration(): void
    {
        $invitation = $this->participantInvitation('invited@example.com', 'old-link-token');
        $this->invitations->saves = 0;

        $withToken = $this->register->execute(new RegisterWithEmailCommand('someone@example.com', 'password123', 'old-link-token'));
        $withoutToken = $this->register->execute(new RegisterWithEmailCommand('other@example.com', 'password123'));

        $this->assertSame(array_keys($withoutToken->toArray()), array_keys($withToken->toArray()), 'Same response shape');
        $this->assertSame($withoutToken->isNewUser, $withToken->isNewUser);
        $credential = $this->emailCredentials->findByEmail('someone@example.com');
        $this->assertNotNull($credential, 'Registered under the submitted address, not the invitation\'s');
        $this->assertNull($this->emailCredentials->findByEmail('invited@example.com'));
        $this->assertFalse($credential->isEmailVerified(), 'The token no longer verifies the address');
        $this->assertSame(['someone@example.com', 'other@example.com'], array_column($this->authMailer->verificationEmails, 'to'), 'The usual confirmation mail is sent');
        $this->assertUntouched($invitation);
    }

    public function test_an_unknown_or_expired_token_registers_normally_instead_of_failing(): void
    {
        $result = $this->register->execute(new RegisterWithEmailCommand('plain@example.com', 'password123', 'no-such-token'));

        $this->assertTrue($result->isNewUser);
        $this->assertFalse($this->emailCredentials->findByEmail('plain@example.com')->isEmailVerified());
        $this->assertCount(1, $this->authMailer->verificationEmails);
    }

    /** D1: the organization invitation token path is unchanged. */
    public function test_an_organization_invitation_token_still_registers_the_invited_address_as_verified(): void
    {
        $invitation = $this->organizationInvitation('member@example.com', 'org-link-token');

        $result = $this->register->execute(new RegisterWithEmailCommand('typed-something-else@example.com', 'password123', 'org-link-token'));

        $credential = $this->emailCredentials->findByEmail('member@example.com');
        $this->assertNotNull($credential);
        $this->assertTrue($credential->isEmailVerified());
        $this->assertSame([], $this->authMailer->verificationEmails);
        $this->assertNotNull($this->memberships->findByOrganizationAndPerson($this->organization->id(), PersonId::fromString($result->personId)));
        $this->assertFalse($this->organizationInvitations->findById($invitation->id())->isPending());
    }
}
