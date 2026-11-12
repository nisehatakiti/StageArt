<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\CancelParticipantCommand;
use StageArt\Application\Participant\CancelParticipantUseCase;
use StageArt\Application\Participant\CreateParticipantCommand;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\GetParticipantQuery;
use StageArt\Application\Participant\GetParticipantUseCase;
use StageArt\Application\Participant\ListParticipantsQuery;
use StageArt\Application\Participant\ListParticipantsUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Participant\ParticipantSubjectNotEligibleException;
use StageArt\Application\Participant\UpdateParticipantCommand;
use StageArt\Application\Participant\UpdateParticipantUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Project\Project;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class ParticipantUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryNotificationEmailRepository $notificationEmails;
    private CreateParticipantUseCase $createParticipant;
    private GetParticipantUseCase $getParticipant;
    private ListParticipantsUseCase $listParticipants;
    private UpdateParticipantUseCase $updateParticipant;
    private CancelParticipantUseCase $cancelParticipant;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->notificationEmails = new InMemoryNotificationEmailRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );
        $personEmailResolver = new PersonEmailResolver(
            $this->people,
            $this->userAccounts,
            $this->emailCredentials,
            new FakeWordPressUserLookup(),
            $this->notificationEmails
        );

        $this->createParticipant = new CreateParticipantUseCase(
            $this->productions,
            $this->participants,
            $this->people,
            $this->organizations,
            $productionAuthorization,
            new InMemoryTransactionManager(),
            $personEmailResolver
        );
        $this->getParticipant = new GetParticipantUseCase($this->participants, $this->productions, $this->people, $productionAuthorization, $personEmailResolver);
        $this->listParticipants = new ListParticipantsUseCase($this->participants, $this->productions, $this->people, $productionAuthorization, $personEmailResolver);
        $this->updateParticipant = new UpdateParticipantUseCase($this->participants, $this->productions, $this->people, $productionAuthorization, $personEmailResolver);
        $this->cancelParticipant = new CancelParticipantUseCase($this->participants, $this->productions, $productionAuthorization);
    }

    private function givenPersonWithVerifiedEmailCredential(int $wordPressUserId, string $email): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);

        $credential = EmailCredential::create($userAccount->id(), $email, 'hash');
        $credential->markEmailVerified();
        $this->emailCredentials->save($credential);

        return $person;
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

    public function test_primary_manager_can_register_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $castPerson = Person::create(2);
        $this->people->save($castPerson);

        $result = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $this->assertSame('ACTIVE', $result->status);
        $this->assertSame('CAST', $result->participantType);
    }

    public function test_delegate_with_participant_manager_role_can_register_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $delegatePerson = Person::create(2);
        $this->people->save($delegatePerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        ));

        $castPerson = Person::create(3);
        $this->people->save($castPerson);

        $result = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            2,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $this->assertSame('CAST', $result->participantType);
    }

    public function test_inactive_delegate_cannot_register_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $delegatePerson = Person::create(2);
        $this->people->save($delegatePerson);
        $delegate = ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        );
        $delegate->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($delegate);

        $castPerson = Person::create(3);
        $this->people->save($castPerson);

        $this->expectException(ParticipantAccessDeniedException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            2,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));
    }

    public function test_unrelated_person_cannot_register_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $outsider = Person::create(2);
        $this->people->save($outsider);

        $castPerson = Person::create(3);
        $this->people->save($castPerson);

        $this->expectException(ParticipantAccessDeniedException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            2,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));
    }

    public function test_subject_person_must_exist(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(ParticipantSubjectNotEligibleException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            PersonId::generate()->toString(),
            'CAST'
        ));
    }

    public function test_duplicate_subject_and_type_registration_is_rejected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $castPerson = Person::create(2);
        $this->people->save($castPerson);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $this->expectException(ParticipantAlreadyExistsException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));
    }

    public function test_same_subject_with_a_different_participant_type_is_allowed(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $this->people->save($person);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $second = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'STAFF'
        ));

        $this->assertSame('STAFF', $second->participantType);
        $this->assertCount(2, $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 1)));
    }

    public function test_get_list_update_and_cancel(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $this->people->save($person);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $fetched = $this->getParticipant->execute(new GetParticipantQuery($created->id, 1));
        $this->assertSame($created->id, $fetched->id);

        $updated = $this->updateParticipant->execute(new UpdateParticipantCommand($created->id, 1, 'STAFF', 'INACTIVE'));
        $this->assertSame('STAFF', $updated->participantType);
        $this->assertSame('INACTIVE', $updated->status);

        $this->cancelParticipant->execute(new CancelParticipantCommand($created->id, 1));

        // Cancel is a Status change, not a physical delete: the record is
        // still retrievable, per Participant.md's "原則として物理削除しない".
        $cancelled = $this->getParticipant->execute(new GetParticipantQuery($created->id, 1));
        $this->assertSame('CANCELLED', $cancelled->status);
    }

    /**
     * StageArt メンバー管理 instruction (担当者権限をメンバー管理へ統合・整理
     * §1): a PERSON-subject Participant's real name is resolved on
     * create/get/list/update, mirroring ProductionDelegateResult's own
     * precedent - the Frontend no longer has to fall back to showing a
     * raw Person ID.
     */
    public function test_person_participants_resolve_the_real_name_on_create_get_list_and_update(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $person->setName('山田', '太郎');
        $this->people->save($person);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));
        $this->assertSame('山田', $created->personFamilyName);
        $this->assertSame('太郎', $created->personGivenName);

        $fetched = $this->getParticipant->execute(new GetParticipantQuery($created->id, 1));
        $this->assertSame('山田', $fetched->personFamilyName);
        $this->assertSame('太郎', $fetched->personGivenName);

        $listed = $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 1));
        $this->assertSame('山田', $listed[0]->personFamilyName);
        $this->assertSame('太郎', $listed[0]->personGivenName);

        $updated = $this->updateParticipant->execute(new UpdateParticipantCommand($created->id, 1, 'STAFF', 'ACTIVE'));
        $this->assertSame('山田', $updated->personFamilyName);
        $this->assertSame('太郎', $updated->personGivenName);
    }

    /**
     * StageArt メンバー一覧メールアドレス表示ラウンド: resolved via the
     * existing PersonEmailResolver (EmailCredential source), on
     * create/get/list/update - never a new field stored on Participant
     * itself.
     */
    public function test_a_person_participants_email_resolves_from_their_email_credential_on_create_get_list_and_update(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $person = $this->givenPersonWithVerifiedEmailCredential(2, 'cast-member@example.com');

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));
        $this->assertSame('cast-member@example.com', $created->email);

        $fetched = $this->getParticipant->execute(new GetParticipantQuery($created->id, 1));
        $this->assertSame('cast-member@example.com', $fetched->email);

        $listed = $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 1));
        $this->assertSame('cast-member@example.com', $listed[0]->email);

        $updated = $this->updateParticipant->execute(new UpdateParticipantCommand($created->id, 1, 'STAFF', 'ACTIVE'));
        $this->assertSame('cast-member@example.com', $updated->email);
    }

    /** §4/既存のメール照合仕様: a verified NotificationEmail is also an
     * accepted source (the same PersonEmailResolver priority chain
     * Settings' own notification-email display already uses), even
     * when the Person has no EmailCredential at all (e.g. Google-only). */
    public function test_a_person_participants_email_resolves_from_a_verified_notification_email_with_no_credential(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create(
            $person->id(),
            'google-notify@example.com',
            true,
            NotificationEmail::SOURCE_GOOGLE
        ));

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $this->assertSame('google-notify@example.com', $created->email);
    }

    /** §4の絶対条件: an UNVERIFIED NotificationEmail must never be shown
     * as if it were a confirmed address - with no other source
     * available, this must resolve to null, not that unverified value. */
    public function test_a_person_participants_email_is_null_when_only_an_unverified_notification_email_exists(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create(
            $person->id(),
            'unverified@example.com',
            false,
            NotificationEmail::SOURCE_GOOGLE
        ));

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $this->assertNull($created->email);
    }

    /** No EmailCredential, no NotificationEmail, no real WordPress
     * user_email - a deliverable address genuinely does not exist
     * anywhere in StageArt for this Person, and that is not an error. */
    public function test_a_person_participants_email_is_null_when_no_source_has_one(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $this->people->save($person);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $this->assertNull($created->email);
    }

    public function test_name_only_participants_have_a_null_email(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'NAME_ONLY',
            null,
            'CAST',
            '山田太郎'
        ));

        $this->assertNull($created->email);
    }

    /**
     * StageArt Production側氏名の権威付けラウンド AC-01: a PERSON
     * Participant's own `displayName` (this Production's record of the
     * member's name) is stored and returned independently of the
     * Person's own familyName/givenName - never overwritten by it.
     */
    public function test_a_person_participants_production_specific_display_name_is_not_overwritten_by_the_persons_own_name(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $person->setName('佐藤', '一郎');
        $this->people->save($person);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST',
            '佐藤一郎（劇団いるか）',
            '主演'
        ));

        $this->assertSame('佐藤一郎（劇団いるか）', $created->displayName);
        $this->assertSame('佐藤', $created->personFamilyName);
        $this->assertSame('一郎', $created->personGivenName);
        $this->assertSame('主演', $created->remarks);

        $fetched = $this->getParticipant->execute(new GetParticipantQuery($created->id, 1));
        $this->assertSame('佐藤一郎（劇団いるか）', $fetched->displayName);

        $listed = $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 1));
        $this->assertSame('佐藤一郎（劇団いるか）', $listed[0]->displayName);
    }

    /**
     * StageArt Production側氏名の権威付けラウンド AC-02: the same Person can
     * carry a different `displayName` in each Production they belong to -
     * ProductionParticipant's own name is per-Production, not resolved
     * from a single canonical Person-level name.
     */
    public function test_the_same_person_can_have_a_different_display_name_in_each_production(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $productionB = $this->givenProductionWithPrimaryManager(2);

        $person = Person::create(3);
        $person->setName('佐藤', '一郎');
        $this->people->save($person);

        $inA = $this->createParticipant->execute(new CreateParticipantCommand(
            $productionA->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST',
            '佐藤一郎（劇団いるか）'
        ));

        $inB = $this->createParticipant->execute(new CreateParticipantCommand(
            $productionB->id()->toString(),
            2,
            'PERSON',
            $person->id()->toString(),
            'STAFF',
            '佐藤一郎'
        ));

        $this->assertSame('佐藤一郎（劇団いるか）', $inA->displayName);
        $this->assertSame('佐藤一郎', $inB->displayName);
    }

    /**
     * A NAME_ONLY Participant has no Person to resolve at all - its own
     * displayName remains the only name field, and person_family_name/
     * person_given_name stay null (not a fabricated fallback).
     */
    public function test_name_only_participants_have_null_person_name_fields(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'NAME_ONLY',
            null,
            'CAST',
            '鈴木花子'
        ));

        $this->assertNull($created->personFamilyName);
        $this->assertNull($created->personGivenName);
        $this->assertSame('鈴木花子', $created->displayName);
    }

    public function test_participant_of_production_a_is_not_accessible_from_production_b(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(2);

        $person = Person::create(3);
        $this->people->save($person);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $productionA->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $this->expectException(ParticipantAccessDeniedException::class);

        // WordPress user 2 is PrimaryManager of Production B only.
        $this->getParticipant->execute(new GetParticipantQuery($created->id, 2));
    }

    public function test_primary_manager_can_register_a_name_only_member_without_a_stageart_account(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'NAME_ONLY',
            null,
            'CAST',
            '山田太郎',
            'チームA'
        ));

        $this->assertSame('NAME_ONLY', $result->subjectType);
        $this->assertSame('山田太郎', $result->displayName);
        $this->assertSame('チームA', $result->remarks);
        $this->assertSame('ACTIVE', $result->status);
    }

    public function test_registering_a_name_only_member_without_a_display_name_is_rejected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(ParticipantSubjectNotEligibleException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'NAME_ONLY',
            null,
            'CAST'
        ));
    }

    public function test_primary_manager_can_update_a_participants_remarks(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $castPerson = Person::create(2);
        $this->people->save($castPerson);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $updated = $this->updateParticipant->execute(new UpdateParticipantCommand(
            $created->id,
            1,
            'CAST',
            'ACTIVE',
            '○○日は出演しないので代わりに出演します'
        ));

        $this->assertSame('○○日は出演しないので代わりに出演します', $updated->remarks);
    }

    /**
     * StageArt 稽古作成画面の参加メンバー表示修正 instruction: the actual fix -
     * a REHEARSAL_MANAGER delegate (who needs to read the Participant
     * roster to pick Rehearsal Attendance targets - see
     * CreateRehearsalUseCase's own MembershipContract::
     * activeProductionMemberPersonIds() check) can now list Participants,
     * via the new Participant.Read Permission RolePermissions::MAP
     * grants them (ProductionAuthorizationService::canReadParticipants()).
     * Before this round, this 403'd (the same population
     * ProductionAuthorizationTest's
     * test_organization_owner_without_primary_manager_or_delegate_status_cannot_read_production-
     * style cases cover for other Read gates).
     */
    public function test_rehearsal_manager_delegate_can_list_participants(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $castPerson = Person::create(2);
        $this->people->save($castPerson);
        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $rehearsalManagerPerson = Person::create(3);
        $this->people->save($rehearsalManagerPerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $rehearsalManagerPerson->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        ));

        $listed = $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 3));

        $this->assertCount(1, $listed);
    }

    /**
     * §重要: REHEARSAL_MANAGER's new Participant.Read must not come with
     * Participant.Create/Update/Delete - those stay PARTICIPANT_MANAGER/
     * PrimaryManager-only via canManageParticipants()'s own unchanged
     * Participant.Update check.
     */
    public function test_rehearsal_manager_delegate_cannot_create_update_or_cancel_participants(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $castPerson = Person::create(2);
        $this->people->save($castPerson);
        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $rehearsalManagerPerson = Person::create(3);
        $this->people->save($rehearsalManagerPerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $rehearsalManagerPerson->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        ));

        $anotherPerson = Person::create(4);
        $this->people->save($anotherPerson);

        try {
            $this->createParticipant->execute(new CreateParticipantCommand(
                $production->id()->toString(),
                3,
                'PERSON',
                $anotherPerson->id()->toString(),
                'STAFF'
            ));
            $this->fail('REHEARSAL_MANAGER must not be able to create a Participant.');
        } catch (ParticipantAccessDeniedException $exception) {
            $this->assertInstanceOf(ParticipantAccessDeniedException::class, $exception);
        }

        try {
            $this->updateParticipant->execute(new UpdateParticipantCommand($created->id, 3, 'STAFF', 'ACTIVE'));
            $this->fail('REHEARSAL_MANAGER must not be able to update a Participant.');
        } catch (ParticipantAccessDeniedException $exception) {
            $this->assertInstanceOf(ParticipantAccessDeniedException::class, $exception);
        }

        try {
            $this->cancelParticipant->execute(new CancelParticipantCommand($created->id, 3));
            $this->fail('REHEARSAL_MANAGER must not be able to cancel a Participant.');
        } catch (ParticipantAccessDeniedException $exception) {
            $this->assertInstanceOf(ParticipantAccessDeniedException::class, $exception);
        }
    }

    /**
     * §PARTICIPANT_MANAGERの既存メンバー管理が壊れない: full regression check
     * via a delegate (not just PrimaryManager, which every other test in
     * this file already exercises) - list, create, update, and cancel
     * all still work unchanged for PARTICIPANT_MANAGER.
     */
    public function test_participant_manager_delegate_retains_full_list_create_update_and_cancel_access(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $participantManagerPerson = Person::create(2);
        $this->people->save($participantManagerPerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $participantManagerPerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        ));

        $castPerson = Person::create(3);
        $this->people->save($castPerson);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            2,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));
        $this->assertSame('ACTIVE', $created->status);

        $this->assertCount(1, $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 2)));

        $updated = $this->updateParticipant->execute(new UpdateParticipantCommand($created->id, 2, 'STAFF', 'ACTIVE'));
        $this->assertSame('STAFF', $updated->participantType);

        $this->cancelParticipant->execute(new CancelParticipantCommand($created->id, 2));
        $cancelled = $this->getParticipant->execute(new GetParticipantQuery($created->id, 2));
        $this->assertSame('CANCELLED', $cancelled->status);
    }

    /**
     * §権限なしユーザーが管理用Participant APIを不正利用できない: neither
     * PrimaryManager, nor any ProductionDelegate Role, nor a plain
     * ACTIVE Participant gains list access from this change - only
     * Participant.Read/Participant.Update holders (and PrimaryManager)
     * do.
     */
    public function test_unrelated_person_cannot_list_participants(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $outsider = Person::create(2);
        $this->people->save($outsider);

        $this->expectException(ParticipantAccessDeniedException::class);

        $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 2));
    }

    /**
     * §稽古対象候補の母集団確認: ListParticipantsUseCase itself returns
     * every Participant regardless of status/subject_type (unchanged -
     * this is the existing Admin-facing roster, still used by the
     * member-management screen) - "ACTIVEかつPERSON" eligibility for
     * Rehearsal target selection is, and remains, a client-side filter
     * matching CoreMembershipAdapter::activeProductionMemberPersonIds()'s
     * own population exactly (Participant Domain semantics are
     * unchanged by this round). This only confirms that population is
     * still reachable through the list this REHEARSAL_MANAGER fix
     * grants - not a new Domain rule.
     */
    public function test_list_participants_includes_every_status_and_subject_type_unfiltered(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $activePerson = Person::create(2);
        $this->people->save($activePerson);
        $activeParticipant = Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $activePerson->id()->toString(),
            ParticipantType::cast()
        );
        $this->participants->save($activeParticipant);

        $inactivePerson = Person::create(3);
        $this->people->save($inactivePerson);
        $inactiveParticipant = Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $inactivePerson->id()->toString(),
            ParticipantType::staff()
        );
        $inactiveParticipant->changeStatus(ParticipantStatus::fromString('INACTIVE'));
        $this->participants->save($inactiveParticipant);

        $cancelledPerson = Person::create(4);
        $this->people->save($cancelledPerson);
        $cancelledParticipant = Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $cancelledPerson->id()->toString(),
            ParticipantType::cast()
        );
        $cancelledParticipant->changeStatus(ParticipantStatus::fromString('CANCELLED'));
        $this->participants->save($cancelledParticipant);

        $this->participants->save(Participant::createNameOnly(
            $production->id(),
            '山田太郎',
            ParticipantType::staff()
        ));

        $listed = $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 1));
        $this->assertCount(4, $listed, 'The Admin-facing list is unfiltered - every status/subject_type still appears.');

        $eligibleForRehearsal = array_filter(
            $listed,
            static fn ($participant) => $participant->subjectType === 'PERSON' && $participant->status === 'ACTIVE'
        );
        $this->assertCount(1, $eligibleForRehearsal, 'Only the ACTIVE, PERSON-subject Participant is Rehearsal-target-eligible.');
        $this->assertSame($activePerson->id()->toString(), array_values($eligibleForRehearsal)[0]->subjectId);
    }
}
