<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

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
use StageArt\Application\OrganizationInvitation\GetMyOrganizationInvitationUseCase;
use StageArt\Application\OrganizationInvitation\OrganizationInvitationNotFoundException;
use StageArt\Application\OrganizationInvitation\ResolveOrganizationInvitationUseCase;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Participant\ParticipantDraftExistsException;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationResult;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationProductionClosedException;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Tests\Support\FakeAccessTokenIssuer;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\FakeGoogleIdTokenVerifier;
use StageArt\Tests\Support\FakeWordPressUserProvisioner;
use StageArt\Tests\Support\InMemoryEmailVerificationTokenRepository;
use StageArt\Tests\Support\InMemoryExternalIdentityRepository;
use StageArt\Tests\Support\InMemoryOrganizationInvitationRepository;
use StageArt\Tests\Support\InMemoryRefreshTokenRepository;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27):
 * organizations and Productions follow the same rule -
 * - an invitation to a verified account registers it at invitation time;
 * - an unregistered address registers on its own registration only once the
 *   address is verified (or signed in with Google with that address);
 * - an unverified, a different or another Person's address registers
 *   nothing; nobody accepts or rejects;
 * - the invitation mail's link opens the invitation by its id, shown only to
 *   the account whose verified address is the invited one.
 */
final class InvitationAutoEnrollmentFlowTest extends TestCase
{
    private const EMAIL = 'newmember@example.com';

    private ParticipantConsentFixture $f;
    private InMemoryOrganizationInvitationRepository $organizationInvitations;
    private FakeAuthMailer $authMailer;
    private FakeGoogleIdTokenVerifier $google;
    private RegisterWithEmailUseCase $register;
    private VerifyEmailUseCase $verify;
    private AuthenticateWithGoogleUseCase $signInWithGoogle;
    private Organization $organization;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->organizationInvitations = new InMemoryOrganizationInvitationRepository();
        $this->authMailer = new FakeAuthMailer();
        $this->google = new FakeGoogleIdTokenVerifier();
        $tokens = new InMemoryEmailVerificationTokenRepository();
        $resolveOrganization = new ResolveOrganizationInvitationUseCase($this->organizationInvitations, $this->f->memberships);
        $resolveParticipant = $this->f->resolve();

        $this->register = new RegisterWithEmailUseCase(
            $this->f->credentials,
            $this->f->people,
            $this->f->userAccounts,
            new InMemoryRefreshTokenRepository(),
            $tokens,
            new FakeAccessTokenIssuer(),
            new FakeWordPressUserProvisioner(500),
            $this->f->transactions,
            $this->authMailer,
            $resolveOrganization,
            $this->organizationInvitations
        );
        $this->verify = new VerifyEmailUseCase($tokens, $this->f->credentials, $this->f->transactions, $this->f->userAccounts, $resolveOrganization, $resolveParticipant);
        $this->signInWithGoogle = new AuthenticateWithGoogleUseCase(
            $this->google,
            new InMemoryExternalIdentityRepository(),
            $this->f->userAccounts,
            $this->f->people,
            new InMemoryRefreshTokenRepository(),
            new FakeAccessTokenIssuer(),
            new FakeWordPressUserProvisioner(700),
            $this->f->transactions,
            new NotificationEmailSeeder($this->f->notificationEmails, $this->f->userAccounts, $this->f->credentials),
            $resolveOrganization,
            $resolveParticipant
        );

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->f->organizations->save($this->organization);
    }

    private function organizationInvitation(string $email, string $expires = 'P1D'): OrganizationInvitation
    {
        $invitation = OrganizationInvitation::create(
            $this->organization->id(),
            $email,
            '佐藤 一郎',
            $this->f->primaryManager->id(),
            null,
            hash('sha256', random_bytes(16)),
            (new DateTimeImmutable())->add(new DateInterval($expires))
        );
        $this->organizationInvitations->save($invitation);

        return $invitation;
    }

    private function isParticipant(PersonId $person, string $type = ParticipantType::CAST): bool
    {
        $row = $this->f->participants->findByProductionAndSubject(
            $this->f->production->id(),
            ParticipantSubjectType::person(),
            $person->toString(),
            ParticipantType::fromString($type)
        );

        return $row !== null && $row->isActive();
    }

    private function isMember(PersonId $person): bool
    {
        $membership = $this->f->memberships->findByOrganizationAndPerson($this->organization->id(), $person);

        return $membership !== null && $membership->isActive();
    }

    private function invite(string $email, string $type = ParticipantType::CAST): CreateParticipantInvitationResult
    {
        return $this->f->createInvitation()->execute(new CreateParticipantInvitationCommand(
            $this->f->production->id()->toString(),
            $this->f->primaryManager->wordPressUserId(),
            '招待 太郎',
            $email,
            $type,
            null
        ));
    }

    // --- 2: a new account, registered once the address is verified --------------

    public function test_a_new_account_is_registered_in_both_only_after_the_address_is_verified(): void
    {
        $participantInvitation = $this->f->invitation(self::EMAIL);
        $organizationInvitation = $this->organizationInvitation(self::EMAIL);

        $result = $this->register->execute(new RegisterWithEmailCommand(self::EMAIL, 'password123'));
        $person = PersonId::fromString($result->personId);

        $this->assertFalse($this->isParticipant($person), '3: an unverified address registers nothing');
        $this->assertFalse($this->isMember($person));
        $this->assertTrue($this->f->invitations->findById($participantInvitation->id())->isPending());

        $this->verify->execute(new VerifyEmailCommand($this->authMailer->verificationEmails[0]['token']));

        $this->assertTrue($this->isParticipant($person), 'Registered as a participant on verification');
        $this->assertTrue($this->isMember($person), 'The same rule for the organization');
        $this->assertSame(ParticipantInvitationStatus::CONSUMED, $this->f->invitations->findById($participantInvitation->id())->status()->toString());
        $this->assertFalse($this->organizationInvitations->findById($organizationInvitation->id())->isPending());
    }

    public function test_a_different_address_is_never_registered(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->organizationInvitation(self::EMAIL);

        $result = $this->register->execute(new RegisterWithEmailCommand('someone-else@example.com', 'password123'));
        $this->verify->execute(new VerifyEmailCommand($this->authMailer->verificationEmails[0]['token']));

        $person = PersonId::fromString($result->personId);
        $this->assertFalse($this->isParticipant($person));
        $this->assertFalse($this->isMember($person));
        $this->assertTrue($this->f->invitations->findById($invitation->id())->isPending());
    }

    public function test_a_cancelled_or_expired_invitation_is_not_registered_on_verification(): void
    {
        $cancelled = $this->f->invitation(self::EMAIL);
        $cancelled->close(ParticipantInvitationCloseReason::manager(), $this->f->primaryManager->id());
        $this->f->invitations->save($cancelled);
        $this->f->invitation(self::EMAIL, null, ParticipantType::STAFF, new DateTimeImmutable('-1 minute'));

        $result = $this->register->execute(new RegisterWithEmailCommand(self::EMAIL, 'password123'));
        $this->verify->execute(new VerifyEmailCommand($this->authMailer->verificationEmails[0]['token']));

        $person = PersonId::fromString($result->personId);
        $this->assertFalse($this->isParticipant($person, ParticipantType::CAST));
        $this->assertFalse($this->isParticipant($person, ParticipantType::STAFF));
    }

    // --- Google ------------------------------------------------------------------------

    public function test_google_with_the_verified_invited_address_registers_in_both(): void
    {
        $this->f->invitation('googleuser@example.com');
        $this->organizationInvitation('googleuser@example.com');
        $this->google->registerValidToken('good-token', 'google-sub-1', 'googleuser@example.com', null, null, true);

        $result = $this->signInWithGoogle->execute(new AuthenticateWithGoogleCommand('good-token'));

        $person = PersonId::fromString($result->personId);
        $this->assertTrue($this->isParticipant($person));
        $this->assertTrue($this->isMember($person));
    }

    public function test_google_with_an_unverified_address_registers_nothing(): void
    {
        $this->f->invitation('googleuser@example.com');
        $this->google->registerValidToken('weak-token', 'google-sub-2', 'googleuser@example.com', null, null, false);

        $result = $this->signInWithGoogle->execute(new AuthenticateWithGoogleCommand('weak-token'));

        $this->assertFalse($this->isParticipant(PersonId::fromString($result->personId)));
    }

    // --- 1: a verified account, registered at invitation time --------------------------

    public function test_a_verified_account_is_registered_at_invitation_time_and_pending_invitations_are_completed(): void
    {
        $person = $this->f->person('verified@example.com');
        $pending = $this->f->invitation('verified@example.com');

        $result = $this->invite('verified@example.com');

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_MEMBER_ADDED, $result->outcome);
        $this->assertTrue($this->isParticipant($person->id()));
        $stored = $this->f->invitations->findById($pending->id());
        $this->assertSame(ParticipantInvitationStatus::CONSUMED, $stored->status()->toString(), 'The earlier invitation to the address is completed');
        $this->assertSame($result->participantId, $stored->participantId()->toString());
        $this->assertSame([], $this->f->mailer->invitationEmails, 'No mail');
    }

    public function test_inviting_an_active_participant_again_is_409_and_changes_nothing(): void
    {
        $person = $this->f->person('verified@example.com');
        $this->invite('verified@example.com');
        $events = $this->f->eventStore->types();

        try {
            $this->invite('verified@example.com');
            $this->fail('409 already_exists expected.');
        } catch (ParticipantAlreadyExistsException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertSame($events, $this->f->eventStore->types());
        $this->assertTrue($this->isParticipant($person->id()));

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_MEMBER_ADDED, $this->invite('verified@example.com', ParticipantType::STAFF)->outcome, 'Another type is another row');
    }

    public function test_a_draft_row_or_a_closed_production_refuses_and_writes_nothing(): void
    {
        $person = $this->f->person('verified@example.com');
        $this->f->participantRow($person, ParticipantStatus::DRAFT);

        try {
            $this->invite('verified@example.com');
            $this->fail('409 draft_exists expected.');
        } catch (ParticipantDraftExistsException $e) {
            $this->assertSame([], $this->f->eventStore->types());
        }

        $this->f->forceProductionStatus(ProductionStatus::COMPLETED);
        $this->expectException(ParticipantInvitationProductionClosedException::class);
        $this->invite('verified@example.com', ParticipantType::STAFF);
    }

    // --- the invitation screen (GET /me/...-invitations/{id}) --------------------------

    public function test_the_production_invitation_screen_states_and_who_may_open_it(): void
    {
        $person = $this->f->person(self::EMAIL);
        $invitation = $this->f->invitation(self::EMAIL);
        $expired = $this->f->invitation(self::EMAIL, null, ParticipantType::STAFF, new DateTimeImmutable('-1 minute'));
        $open = fn (Person $as, string $id): array => $this->f->getMine()->execute($as->wordPressUserId(), $id);

        $this->assertSame('PENDING', $open($person, $invitation->id()->toString())['status']);
        $this->assertSame('EXPIRED', $open($person, $expired->id()->toString())['status']);

        $this->f->resolve()->execute($person->id(), self::EMAIL);
        $this->assertSame('REGISTERED', $open($person, $invitation->id()->toString())['status'], '10: opening the mail again after registration shows it');
        $this->assertSame($this->f->production->id()->toString(), $open($person, $invitation->id()->toString())['production_id']);

        $cancelled = $this->f->invitation(self::EMAIL, null, ParticipantType::STAFF);
        $cancelled->close(ParticipantInvitationCloseReason::manager(), $this->f->primaryManager->id());
        $this->f->invitations->save($cancelled);
        $this->assertSame('CANCELLED', $open($person, $cancelled->id()->toString())['status']);

        foreach ([
            [$this->f->person('other@example.com'), $invitation->id()->toString()],
            [$this->f->person('unverified@example.com', false), $this->f->invitation('unverified@example.com')->id()->toString()],
            [$person, 'not-a-uuid'],
        ] as [$as, $id]) {
            try {
                $open($as, $id);
                $this->fail('404 expected.');
            } catch (ParticipantInvitationNotFoundException $e) {
                $this->assertSame('Participant invitation not found.', $e->getMessage(), '5: nothing is shown to another account');
            }
        }
    }

    public function test_the_organization_invitation_screen_follows_the_same_rule(): void
    {
        $person = $this->f->person(self::EMAIL);
        $invitation = $this->organizationInvitation(self::EMAIL);
        $screen = new GetMyOrganizationInvitationUseCase($this->f->verifier(), $this->organizationInvitations, $this->f->organizations, $this->f->memberships);

        $shown = $screen->execute($person->wordPressUserId(), $invitation->id()->toString());
        $this->assertSame(['id', 'organization_id', 'organization_name', 'status'], array_keys($shown));
        $this->assertSame(['Theatre Co', 'PENDING'], [$shown['organization_name'], $shown['status']]);

        (new ResolveOrganizationInvitationUseCase($this->organizationInvitations, $this->f->memberships))->execute($person->id(), self::EMAIL);
        $this->assertSame('REGISTERED', $screen->execute($person->wordPressUserId(), $invitation->id()->toString())['status']);

        $other = $this->f->person('other@example.com');

        foreach ([[$other, $invitation->id()->toString()], [$person, 'not-a-uuid']] as [$as, $id]) {
            try {
                $screen->execute($as->wordPressUserId(), $id);
                $this->fail('404 expected.');
            } catch (OrganizationInvitationNotFoundException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
