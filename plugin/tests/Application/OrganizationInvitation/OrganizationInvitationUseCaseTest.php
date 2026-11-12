<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\OrganizationInvitation;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Membership\MembershipAlreadyExistsException;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\OrganizationInvitation\CancelOrganizationInvitationCommand;
use StageArt\Application\OrganizationInvitation\CancelOrganizationInvitationUseCase;
use StageArt\Application\OrganizationInvitation\CreateOrganizationInvitationCommand;
use StageArt\Application\OrganizationInvitation\CreateOrganizationInvitationResult;
use StageArt\Application\OrganizationInvitation\CreateOrganizationInvitationUseCase;
use StageArt\Application\OrganizationInvitation\GetOrganizationInvitationByTokenQuery;
use StageArt\Application\OrganizationInvitation\GetOrganizationInvitationByTokenUseCase;
use StageArt\Application\OrganizationInvitation\ListOrganizationInvitationsQuery;
use StageArt\Application\OrganizationInvitation\ListOrganizationInvitationsUseCase;
use StageArt\Application\OrganizationInvitation\OrganizationInvitationNotFoundException;
use StageArt\Application\OrganizationInvitation\OrganizationInvitationNotPendingException;
use StageArt\Application\OrganizationInvitation\ResendOrganizationInvitationCommand;
use StageArt\Application\OrganizationInvitation\ResendOrganizationInvitationUseCase;
use StageArt\Application\OrganizationInvitation\ResolveOrganizationInvitationUseCase;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\FakeOrganizationInvitationMailer;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryOrganizationInvitationRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class OrganizationInvitationUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationInvitationRepository $invitations;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryUserAccountRepository $userAccounts;
    private FakeOrganizationInvitationMailer $mailer;
    private OrganizationAuthorizationService $authorization;

    private CreateOrganizationInvitationUseCase $createInvitation;
    private ResendOrganizationInvitationUseCase $resendInvitation;
    private GetOrganizationInvitationByTokenUseCase $getInvitationByToken;
    private ResolveOrganizationInvitationUseCase $resolveInvitation;
    private ListOrganizationInvitationsUseCase $listInvitations;
    private CancelOrganizationInvitationUseCase $cancelInvitation;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->invitations = new InMemoryOrganizationInvitationRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->mailer = new FakeOrganizationInvitationMailer();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);

        $findPersonByEmail = new FindPersonByEmailUseCase(
            $this->emailCredentials,
            $this->userAccounts,
            new InMemoryNotificationEmailRepository(),
            $this->people
        );

        $this->resendInvitation = new ResendOrganizationInvitationUseCase(
            $this->invitations,
            $this->organizations,
            $this->authorization,
            $this->mailer
        );
        $this->createInvitation = new CreateOrganizationInvitationUseCase(
            $this->organizations,
            $this->authorization,
            $findPersonByEmail,
            $this->memberships,
            $this->invitations,
            $this->resendInvitation,
            $this->mailer
        );
        $this->getInvitationByToken = new GetOrganizationInvitationByTokenUseCase($this->invitations, $this->organizations);
        $this->resolveInvitation = new ResolveOrganizationInvitationUseCase($this->invitations, $this->memberships);
        $this->listInvitations = new ListOrganizationInvitationsUseCase($this->invitations, $this->organizations, $this->authorization);
        $this->cancelInvitation = new CancelOrganizationInvitationUseCase($this->invitations, $this->organizations, $this->authorization);
    }

    private function givenOrganizationWithOwner(int $ownerWordPressUserId): Organization
    {
        $organization = Organization::create(new OrganizationName('Test Theatre'));
        $this->organizations->save($organization);

        $owner = Person::create($ownerWordPressUserId);
        $this->people->save($owner);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));

        return $organization;
    }

    private function givenRegisteredPerson(int $wordPressUserId, string $email): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);

        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), $email, password_hash('irrelevant', PASSWORD_DEFAULT)));

        return $person;
    }

    public function test_adding_an_existing_registered_person_by_email_creates_an_active_membership_directly(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $existingPerson = $this->givenRegisteredPerson(2, 'existing@example.com');

        $result = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '既存メンバー',
            'existing@example.com'
        ));

        $this->assertSame(CreateOrganizationInvitationResult::OUTCOME_MEMBER_ADDED, $result->outcome);
        $this->assertNotNull($result->member);
        $this->assertNull($result->invitation);

        $membership = $this->memberships->findByOrganizationAndPerson($organization->id(), $existingPerson->id());
        $this->assertNotNull($membership);
        $this->assertTrue($membership->isActive());
        $this->assertSame(RoleKey::MEMBER, $membership->roleKey()->toString());
        $this->assertEmpty($this->mailer->invitationEmails);
    }

    public function test_adding_an_existing_registered_person_carries_remarks_onto_the_membership(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $existingPerson = $this->givenRegisteredPerson(2, 'existing@example.com');

        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '既存メンバー',
            'existing@example.com',
            '衣装合わせ待ち'
        ));

        $membership = $this->memberships->findByOrganizationAndPerson($organization->id(), $existingPerson->id());
        $this->assertSame('衣装合わせ待ち', $membership->remarks());
    }

    public function test_adding_an_already_active_member_is_rejected(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $existingPerson = $this->givenRegisteredPerson(2, 'existing@example.com');
        $this->memberships->save(Membership::create($organization->id(), $existingPerson->id(), RoleKey::member()));

        $this->expectException(MembershipAlreadyExistsException::class);

        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '既存メンバー',
            'existing@example.com'
        ));
    }

    public function test_adding_an_unregistered_email_creates_a_pending_invitation_and_sends_mail(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);

        $result = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com',
            '備考です'
        ));

        $this->assertSame(CreateOrganizationInvitationResult::OUTCOME_INVITATION_CREATED, $result->outcome);
        $this->assertNull($result->member);
        $this->assertNotNull($result->invitation);
        $this->assertSame('newcomer@example.com', $result->invitation->email);
        $this->assertSame('新規招待', $result->invitation->invitedName);
        $this->assertSame('備考です', $result->invitation->remarks);
        $this->assertCount(1, $this->mailer->invitationEmails);
        $this->assertSame('newcomer@example.com', $this->mailer->invitationEmails[0]['to']);
    }

    public function test_adding_the_same_unregistered_email_twice_resends_instead_of_duplicating(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $first = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));

        $second = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));

        $this->assertSame(CreateOrganizationInvitationResult::OUTCOME_INVITATION_RESENT, $second->outcome);
        $this->assertSame($first->invitation->id, $second->invitation->id);
        $this->assertCount(2, $this->mailer->invitationEmails);
    }

    public function test_plain_member_cannot_create_an_invitation(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $member = Person::create(5);
        $this->people->save($member);
        $this->memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            5,
            'Name',
            'someone@example.com'
        ));
    }

    public function test_create_rejects_an_invalid_email(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);

        $this->expectException(InvalidArgumentException::class);

        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            'Name',
            'not-an-email'
        ));
    }

    public function test_create_on_an_unknown_organization_is_not_found(): void
    {
        $requester = Person::create(1);
        $this->people->save($requester);

        $this->expectException(OrganizationNotFoundException::class);

        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            OrganizationId::generate()->toString(),
            1,
            'Name',
            'someone@example.com'
        ));
    }

    public function test_get_by_token_returns_a_preview_for_a_usable_invitation(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $created = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));
        $token = $this->mailer->invitationEmails[0]['token'];

        $preview = $this->getInvitationByToken->execute(new GetOrganizationInvitationByTokenQuery($token));

        $this->assertSame('Test Theatre', $preview->organizationName);
        $this->assertSame('newcomer@example.com', $preview->email);
        $this->assertSame('新規招待', $preview->invitedName);
    }

    public function test_get_by_token_rejects_an_unknown_token(): void
    {
        $this->expectException(OrganizationInvitationNotFoundException::class);

        $this->getInvitationByToken->execute(new GetOrganizationInvitationByTokenQuery('not-a-real-token'));
    }

    public function test_resend_rotates_the_token_and_sends_another_mail(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $created = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));
        $firstToken = $this->mailer->invitationEmails[0]['token'];

        $this->resendInvitation->execute(new ResendOrganizationInvitationCommand($created->invitation->id, 1));

        $secondToken = $this->mailer->invitationEmails[1]['token'];
        $this->assertNotSame($firstToken, $secondToken);
        $this->expectException(OrganizationInvitationNotFoundException::class);
        $this->getInvitationByToken->execute(new GetOrganizationInvitationByTokenQuery($firstToken));
    }

    public function test_resolve_invitation_creates_a_membership_once_the_invitee_registers(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));
        $newPerson = Person::create(10);
        $this->people->save($newPerson);

        $this->resolveInvitation->execute($newPerson->id(), 'newcomer@example.com');

        $membership = $this->memberships->findByOrganizationAndPerson($organization->id(), $newPerson->id());
        $this->assertNotNull($membership);
        $this->assertTrue($membership->isActive());
        $this->assertSame(RoleKey::MEMBER, $membership->roleKey()->toString());
    }

    public function test_resolve_invitation_carries_remarks_onto_the_new_membership(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com',
            '初回公演から参加予定'
        ));
        $newPerson = Person::create(10);
        $this->people->save($newPerson);

        $this->resolveInvitation->execute($newPerson->id(), 'newcomer@example.com');

        $membership = $this->memberships->findByOrganizationAndPerson($organization->id(), $newPerson->id());
        $this->assertSame('初回公演から参加予定', $membership->remarks());
    }

    public function test_resolve_invitation_marks_it_consumed(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $created = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));
        $newPerson = Person::create(10);
        $this->people->save($newPerson);

        $this->resolveInvitation->execute($newPerson->id(), 'newcomer@example.com');

        $token = $this->mailer->invitationEmails[0]['token'];
        $this->expectException(OrganizationInvitationNotFoundException::class);
        $this->getInvitationByToken->execute(new GetOrganizationInvitationByTokenQuery($token));
    }

    public function test_resolve_invitation_for_an_email_with_no_outstanding_invitation_is_a_silent_no_op(): void
    {
        $newPerson = Person::create(10);
        $this->people->save($newPerson);

        $this->resolveInvitation->execute($newPerson->id(), 'nobody-invited-this@example.com');

        $this->assertCount(0, $this->memberships->findByPersonId($newPerson->id()));
    }

    public function test_list_invitations_returns_every_invitation_for_the_organization(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待A',
            'a@example.com'
        ));
        $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待B',
            'b@example.com'
        ));

        $results = $this->listInvitations->execute(new ListOrganizationInvitationsQuery($organization->id()->toString(), 1));

        $this->assertCount(2, $results);
    }

    public function test_list_invitations_is_denied_for_a_plain_member(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $member = Person::create(5);
        $this->people->save($member);
        $this->memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->listInvitations->execute(new ListOrganizationInvitationsQuery($organization->id()->toString(), 5));
    }

    public function test_list_invitations_on_an_unknown_organization_is_not_found(): void
    {
        $requester = Person::create(1);
        $this->people->save($requester);

        $this->expectException(OrganizationNotFoundException::class);

        $this->listInvitations->execute(new ListOrganizationInvitationsQuery(OrganizationId::generate()->toString(), 1));
    }

    public function test_cancel_marks_a_pending_invitation_cancelled_and_it_is_no_longer_resolvable_by_token(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $created = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));
        $token = $this->mailer->invitationEmails[0]['token'];

        $result = $this->cancelInvitation->execute(new CancelOrganizationInvitationCommand($created->invitation->id, 1));

        $this->assertSame('CANCELLED', $result->status);
        $this->expectException(OrganizationInvitationNotFoundException::class);
        $this->getInvitationByToken->execute(new GetOrganizationInvitationByTokenQuery($token));
    }

    public function test_cancel_is_denied_for_a_plain_member(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $created = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));
        $member = Person::create(5);
        $this->people->save($member);
        $this->memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->cancelInvitation->execute(new CancelOrganizationInvitationCommand($created->invitation->id, 5));
    }

    public function test_cancel_an_already_cancelled_invitation_is_rejected(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $created = $this->createInvitation->execute(new CreateOrganizationInvitationCommand(
            $organization->id()->toString(),
            1,
            '新規招待',
            'newcomer@example.com'
        ));
        $this->cancelInvitation->execute(new CancelOrganizationInvitationCommand($created->invitation->id, 1));

        $this->expectException(OrganizationInvitationNotPendingException::class);

        $this->cancelInvitation->execute(new CancelOrganizationInvitationCommand($created->invitation->id, 1));
    }

    public function test_cancel_an_unknown_invitation_is_not_found(): void
    {
        $this->givenOrganizationWithOwner(1);

        $this->expectException(OrganizationInvitationNotFoundException::class);

        $this->cancelInvitation->execute(new CancelOrganizationInvitationCommand(
            \StageArt\Domain\OrganizationInvitation\OrganizationInvitationId::generate()->toString(),
            1
        ));
    }
}
