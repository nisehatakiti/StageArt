<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ProductionDelegate;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Participant\ApproveParticipantRequestCommand;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\WithdrawParticipationCommand;
use StageArt\Application\Production\ListProductionsForPersonQuery;
use StageArt\Application\Production\ListProductionsUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesQuery;
use StageArt\Application\ProductionDelegate\ProductionDelegateTargetNotParticipantException;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateCommand;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Core\Adapter\CoreProductionCapabilityDirectoryAdapter;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\Person\Person;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §4.6 / §7.1 / §8 (stage 4, T40-T50, T75-T79, T86-T88): a
 * ProductionDelegate row grants its Role only while the row is ACTIVE AND
 * the Person is an ACTIVE PERSON Participant. Since the 2026-11-27
 * invitation policy (user decision) recorded consent (consented_at) is no
 * longer part of the condition. The PrimaryManager is unaffected. Rows are
 * never changed by the check itself; appointment requires the same
 * condition.
 */
final class DelegateEffectivenessTest extends TestCase
{
    private ParticipantConsentFixture $f;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
    }

    private function delegatePerson(string $email = 'taro@example.com'): Person
    {
        return $this->f->person($email);
    }

    /** Everything the permission checks answer for this Person. */
    private function permissions(Person $person): array
    {
        $auth = $this->f->authorization;
        $production = $this->f->production;

        return [
            'manage_participants' => $auth->canManageParticipants($person, $production),
            'read_participants' => $auth->canReadParticipants($person, $production),
            'read_production' => $auth->canReadProduction($person, $production),
            'rehearsal' => $auth->hasProductionCapability($person, $production, 'Rehearsal.Update'),
            'accounting' => $auth->hasProductionCapability($person, $production, 'Accounting.Update'),
            'delegate_row' => $auth->activeDelegateFor($person, $production) !== null,
        ];
    }

    private static function none(): array
    {
        return array_fill_keys(['manage_participants', 'read_participants', 'read_production', 'rehearsal', 'accounting', 'delegate_row'], false);
    }

    private function giveRoles(Person $person): void
    {
        $this->f->delegate($person, RoleKey::PARTICIPANT_MANAGER);
        $this->f->delegate($person, RoleKey::REHEARSAL_MANAGER);
        $this->f->delegate($person, RoleKey::ACCOUNTING_MANAGER);
    }

    private function accept(Person $person, string $email, string $type = ParticipantType::CAST): void
    {
        // Invitation policy 2026-11-27: registered once the address is verified (the R14-B acceptance before).
        $this->f->invitation($email, null, $type);
        $this->f->resolve()->execute($person->id(), $email);
    }

    private function appoint(Person $person, string $role = RoleKey::PARTICIPANT_MANAGER): void
    {
        $this->f->createDelegate()->execute(new CreateProductionDelegateCommand(
            $this->f->production->id()->toString(),
            $this->f->primaryManager->wordPressUserId(),
            $person->id()->toString(),
            $role
        ));
    }

    // --- the condition ----------------------------------------------

    /** T40: an ACTIVE row of a Person who is not a participant grants nothing; the row is not changed. */
    public function test_an_active_delegate_row_of_a_non_participant_grants_nothing(): void
    {
        $person = $this->delegatePerson();
        $this->giveRoles($person);

        $this->assertSame(self::none(), $this->permissions($person));
        $this->assertFalse($this->f->authorization->isProductionMember($person, $this->f->production));

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $row) {
            $this->assertTrue($row->isActive(), 'The check never changes the row');
        }
    }

    /**
     * Invitation policy 2026-11-27 (user decision): an ACTIVE PERSON Participant
     * without recorded consent - existing data or one registered from an
     * invitation - now has its delegate rows' permissions (T75 before: none).
     */
    public function test_an_active_participant_without_consent_has_the_roles_permissions(): void
    {
        $person = $this->delegatePerson();
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $this->giveRoles($person);

        $this->assertSame(array_fill_keys(array_keys(self::none()), true), $this->permissions($person));
        $this->assertTrue($this->f->authorization->isActivePersonParticipant($person, $this->f->production));
        $this->assertTrue($this->f->authorization->isProductionMember($person, $this->f->production));
    }

    /** T48: a consented ACTIVE PERSON Participant's ACTIVE rows grant their Roles. */
    public function test_a_consented_active_participant_has_the_roles_permissions(): void
    {
        $person = $this->delegatePerson();
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $this->giveRoles($person);

        $this->assertSame(array_fill_keys(array_keys(self::none()), true), $this->permissions($person));
    }

    /** §4.6: either of the two types may carry the consent. */
    public function test_consent_on_either_type_row_counts(): void
    {
        $person = $this->delegatePerson();
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::CAST);
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $this->f->delegate($person);

        $this->assertTrue($this->f->authorization->canManageParticipants($person, $this->f->production));
    }

    public function test_consent_on_a_non_active_row_does_not_count(): void
    {
        $person = $this->delegatePerson();
        $withdrawn = $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $withdrawn->withdraw();
        $this->f->participants->save($withdrawn);
        $this->f->delegate($person);

        $this->assertFalse($this->f->authorization->canManageParticipants($person, $this->f->production));
    }

    public function test_a_name_only_row_is_never_a_person_participant(): void
    {
        $person = $this->delegatePerson();
        $nameOnly = Participant::reconstitute(
            ParticipantId::generate(),
            $this->f->production->id(),
            ParticipantSubjectType::nameOnly(),
            $person->id()->toString(),
            ParticipantType::staff(),
            ParticipantStatus::active(),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-01'),
            null,
            '名前だけ',
            ParticipantEnrollmentSource::managerRecord(),
            \StageArt\Domain\Participant\ParticipantConsentSource::invitation(),
            new DateTimeImmutable('2026-01-02')
        );
        $this->f->participants->save($nameOnly);
        $this->f->delegate($person);

        $this->assertFalse($this->f->authorization->canManageParticipants($person, $this->f->production));
    }

    public function test_an_inactive_row_grants_nothing_even_for_a_consented_participant(): void
    {
        $person = $this->delegatePerson();
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $this->f->delegate($person, RoleKey::PARTICIPANT_MANAGER, false);

        $this->assertSame(self::none(), $this->permissions($person));
    }

    /** T47: the PrimaryManager keeps everything without being a participant or consenting. */
    public function test_the_primary_manager_is_not_affected(): void
    {
        $pm = $this->f->primaryManager;

        $this->assertTrue($this->f->authorization->canManageParticipants($pm, $this->f->production));
        $this->assertTrue($this->f->authorization->canReadProduction($pm, $this->f->production));
        $this->assertTrue($this->f->authorization->hasProductionCapability($pm, $this->f->production, 'Accounting.Update'));
        $this->assertTrue($this->f->authorization->canManageProductionDelegates($pm, $this->f->production));
        $this->assertSame([], $this->f->participants->findPersonParticipants($this->f->production->id(), $pm->id()->toString()));
    }

    /** A failed participant read is never read as "no permission" or "permission". */
    public function test_a_failed_participant_read_throws(): void
    {
        $person = $this->delegatePerson();
        $this->f->delegate($person);
        $this->f->participants->failNextConsentRead = new PersistenceException('read from', 'wp_stageart_participants', 1146);

        $this->expectException(PersistenceException::class);
        $this->f->authorization->canManageParticipants($person, $this->f->production);
    }

    // --- APIs behind the permission (T41, T42, T50) -------------------

    /** T41: an ineffective delegate is refused by a UseCase that needs the Role (403). */
    public function test_an_ineffective_delegate_is_refused_by_the_usecases(): void
    {
        $person = $this->delegatePerson();
        $this->f->delegate($person);
        $requester = $this->f->person('req@example.com');
        $request = $this->f->participantRow($requester, ParticipantStatus::PENDING, ParticipantType::CAST, ParticipantEnrollmentSource::JOIN_REQUEST, new DateTimeImmutable('-1 day'));

        $this->expectException(ParticipantAccessDeniedException::class);
        $this->f->approveRequest()->execute(new ApproveParticipantRequestCommand($person->wordPressUserId(), $request->id()->toString()));
    }

    /** T42: the own Production list shows a delegate's Production only while the row is effective. */
    public function test_the_production_list_follows_the_effectiveness(): void
    {
        $person = $this->delegatePerson();
        $this->f->delegate($person);
        $list = new ListProductionsUseCase($this->f->productions, $this->f->delegates, $this->f->authorization);
        $ids = fn (): array => array_map(static fn ($result) => $result->id, $list->execute(new ListProductionsForPersonQuery($person->wordPressUserId())));

        $this->assertSame([], $ids(), 'Not a participant');

        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $this->assertSame([$this->f->production->id()->toString()], $ids(), 'An ACTIVE participant, with or without consent');
        $this->assertSame([$this->f->production->id()], $this->f->authorization->effectiveDelegateProductionIds($person->id()));
    }

    /** T50: the accounting notification recipients (CoreProductionCapabilityDirectoryAdapter) leave ineffective delegates out. */
    public function test_the_capability_directory_leaves_ineffective_delegates_out(): void
    {
        $effective = $this->delegatePerson('a@example.com');
        $this->f->participantRow($effective, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $this->f->delegate($effective, RoleKey::ACCOUNTING_MANAGER);
        $unconsented = $this->delegatePerson('b@example.com');
        $this->f->participantRow($unconsented, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $this->f->delegate($unconsented, RoleKey::ACCOUNTING_MANAGER);
        $outsider = $this->delegatePerson('c@example.com');
        $this->f->delegate($outsider, RoleKey::ACCOUNTING_MANAGER);

        $directory = new CoreProductionCapabilityDirectoryAdapter($this->f->authorization, $this->f->productions, $this->f->delegates, $this->f->people);
        $holders = array_map(static fn ($id): string => $id->toString(), $directory->personIdsWithProductionCapability($this->f->production->id(), 'Accounting.Update'));

        sort($holders);
        $expected = [$this->f->primaryManager->id()->toString(), $effective->id()->toString(), $unconsented->id()->toString()];
        sort($expected);
        $this->assertSame($expected, $holders, 'The non-participant is left out; consent no longer matters');
    }

    // --- lifecycle (T43-T45, T76, T86-T88) -----------------------------

    /** T43 / T44: a non-participant's old row goes INACTIVE on acceptance; appointing again reuses that row. */
    public function test_an_old_appointment_does_not_come_back_on_acceptance_and_is_reused_on_reappointment(): void
    {
        $person = $this->delegatePerson();
        $old = $this->f->delegate($person);

        $this->accept($person, 'taro@example.com');

        $this->assertFalse($this->f->delegates->findById($old->id())->isActive(), 'DORMANT_ON_ENROLLMENT');
        $this->assertFalse($this->f->authorization->canManageParticipants($person, $this->f->production));

        $this->f->watchProductionLock();
        $this->appoint($person);

        $rows = $this->f->delegates->findByProductionId($this->f->production->id());
        $this->assertCount(1, $rows, 'The same row, no second one');
        $this->assertTrue($rows[0]->id()->equals($old->id()));
        $this->assertTrue($rows[0]->isActive());
        $this->assertTrue($this->f->authorization->canManageParticipants($person, $this->f->production));
        $this->assertSame(['production', 'participant', 'delegate'], $this->f->lockLog->order(), '§5.4 lock order');
        $this->assertSame(ParticipantEvent::DELEGATE_REACTIVATED, $this->f->eventStore->all()[count($this->f->eventStore->all()) - 1]->eventType());
    }

    /** T76 / T86 (U15): an ACTIVE participant's ACTIVE row is effective; an invitation to them changes nothing; an INACTIVE row stays. */
    public function test_an_invitation_to_an_active_participant_keeps_active_rows_and_leaves_inactive_rows(): void
    {
        $person = $this->delegatePerson();
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::CAST);
        $active = $this->f->delegate($person, RoleKey::PARTICIPANT_MANAGER);
        $inactive = $this->f->delegate($person, RoleKey::REHEARSAL_MANAGER, false);

        $this->assertTrue($this->f->authorization->canManageParticipants($person, $this->f->production));

        $this->accept($person, 'taro@example.com');

        $this->assertTrue($this->f->delegates->findById($active->id())->isActive());
        $this->assertFalse($this->f->delegates->findById($inactive->id())->isActive());
        $this->assertTrue($this->f->authorization->canManageParticipants($person, $this->f->production));
        $this->assertFalse($this->f->authorization->hasProductionCapability($person, $this->f->production, 'Rehearsal.Update'));
    }

    /** T45 / T87: withdrawal ends the permission; re-accepting does not bring the rows back; reappointing does. */
    public function test_withdrawal_and_return_need_a_new_appointment(): void
    {
        $person = $this->delegatePerson();
        $participant = $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::CAST, null, null, true);
        $row = $this->f->delegate($person);
        $this->assertTrue($this->f->authorization->canManageParticipants($person, $this->f->production));

        $this->f->withdraw()->execute(new WithdrawParticipationCommand($person->wordPressUserId(), $participant->id()->toString()));

        $this->assertFalse($this->f->delegates->findById($row->id())->isActive());
        $this->assertFalse($this->f->authorization->canManageParticipants($person, $this->f->production));
        $this->assertNotNull($this->f->delegates->findById($row->id()), 'Never deleted');

        $this->accept($person, 'taro@example.com');

        $this->assertFalse($this->f->delegates->findById($row->id())->isActive(), 'U15: not brought back');
        $this->assertFalse($this->f->authorization->canManageParticipants($person, $this->f->production));

        $this->appoint($person);
        $this->assertTrue($this->f->authorization->canManageParticipants($person, $this->f->production));
    }

    // --- appointment (T77-T79) -----------------------------------------

    public function test_appointing_a_non_participant_is_refused(): void
    {
        $person = $this->delegatePerson();

        $this->expectException(ProductionDelegateTargetNotParticipantException::class);
        $this->appoint($person);
    }

    /** T77 (changed by the invitation policy 2026-11-27): an ACTIVE participant without recorded consent can be appointed. */
    public function test_an_active_participant_without_consent_can_be_appointed(): void
    {
        $person = $this->delegatePerson();
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF);

        $this->appoint($person);

        $this->assertCount(1, $this->f->delegates->findByProductionId($this->f->production->id()));
        $this->assertTrue($this->f->authorization->canManageParticipants($person, $this->f->production));
    }

    /** T78 / T79: an approved join request can be appointed, R14-B (with consent) or pre-R14-B (without). */
    public function test_an_approved_join_request_can_be_appointed(): void
    {
        $person = $this->delegatePerson();
        $request = $this->f->participantRow($person, ParticipantStatus::PENDING, ParticipantType::CAST, ParticipantEnrollmentSource::JOIN_REQUEST, new DateTimeImmutable('-1 day'));
        $this->f->approveRequest()->execute(new ApproveParticipantRequestCommand($this->f->primaryManager->wordPressUserId(), $request->id()->toString()));
        $this->appoint($person);
        $this->assertTrue($this->f->authorization->canManageParticipants($person, $this->f->production));

        $legacy = $this->delegatePerson('legacy@example.com');
        $old = $this->f->participantRow($legacy, ParticipantStatus::PENDING, ParticipantType::CAST);
        $this->f->approveRequest()->execute(new ApproveParticipantRequestCommand($this->f->primaryManager->wordPressUserId(), $old->id()->toString()));
        $this->appoint($legacy);
        $this->assertTrue($this->f->authorization->canManageParticipants($legacy, $this->f->production));
    }

    public function test_appointment_rolls_back_on_a_failed_participant_read(): void
    {
        $person = $this->delegatePerson();
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $this->f->participants->failNextConsentRead = new PersistenceException('read from', 'wp_stageart_participants', 1146);

        try {
            $this->appoint($person);
            $this->fail('The failure must surface.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([], $this->f->delegates->findByProductionId($this->f->production->id()));
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame(1, $this->f->transactions->rollbacks());
    }

    /** §7.1 PUT /production-delegates/{id}: making a row ACTIVE again (or giving it another Role) is an appointment. */
    public function test_reactivating_or_changing_the_role_needs_an_eligible_person_but_deactivating_does_not(): void
    {
        $person = $this->delegatePerson();
        $row = $this->f->delegate($person, RoleKey::PARTICIPANT_MANAGER, false);
        $update = fn (string $role, string $status) => $this->f->updateDelegate()->execute(
            new UpdateProductionDelegateCommand($row->id()->toString(), $this->f->primaryManager->wordPressUserId(), $role, $status)
        );

        try {
            $update(RoleKey::PARTICIPANT_MANAGER, ProductionDelegate::STATUS_ACTIVE);
            $this->fail('422 expected.');
        } catch (ProductionDelegateTargetNotParticipantException $e) {
            $this->assertFalse($this->f->delegates->findById($row->id())->isActive(), 'Unchanged');
        }

        $member = $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $result = $update(RoleKey::PARTICIPANT_MANAGER, ProductionDelegate::STATUS_ACTIVE);
        $this->assertTrue($result->effective, 'An ACTIVE participant, no consent needed');
        $this->assertNull($result->ineffectiveReason);

        $member->withdraw();
        $this->f->participants->save($member);

        try {
            $update(RoleKey::REHEARSAL_MANAGER, ProductionDelegate::STATUS_ACTIVE);
            $this->fail('A Role change of an ineffective row is an appointment: 422 expected.');
        } catch (ProductionDelegateTargetNotParticipantException $e) {
            $this->assertTrue($this->f->delegates->findById($row->id())->role()->equals(RoleKey::participantManager()));
        }

        $result = $update(RoleKey::PARTICIPANT_MANAGER, ProductionDelegate::STATUS_INACTIVE);
        $this->assertSame('INACTIVE', $result->ineffectiveReason);
        $this->assertSame(
            [ParticipantEvent::DELEGATE_REACTIVATED, ParticipantEvent::DELEGATE_DEACTIVATED],
            $this->f->eventStore->types()
        );
        $this->assertSame('MANAGER', $this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED)[0]->detail()['reason']);
    }

    // --- the list (T49) --------------------------------------------------

    public function test_the_delegate_list_tells_effective_from_ineffective(): void
    {
        $effective = $this->delegatePerson('a@example.com');
        $this->f->participantRow($effective, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $rowEffective = $this->f->delegate($effective);
        $rowInactive = $this->f->delegate($effective, RoleKey::REHEARSAL_MANAGER, false);
        $unconsented = $this->delegatePerson('b@example.com');
        $this->f->participantRow($unconsented, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $rowUnconsented = $this->f->delegate($unconsented);
        $outsider = $this->delegatePerson('c@example.com');
        $rowOutsider = $this->f->delegate($outsider);

        $items = [];

        foreach ($this->f->listDelegates()->execute(new ListProductionDelegatesQuery($this->f->production->id()->toString(), $this->f->primaryManager->wordPressUserId())) as $result) {
            $items[$result->id] = [$result->toArray()['effective'], $result->toArray()['ineffective_reason']];
        }

        $this->assertSame([true, null], $items[$rowEffective->id()->toString()]);
        $this->assertSame([false, ProductionAuthorizationService::DELEGATE_INACTIVE], $items[$rowInactive->id()->toString()]);
        $this->assertSame([true, null], $items[$rowUnconsented->id()->toString()], 'Consent no longer matters (2026-11-27)');
        $this->assertSame([false, ProductionAuthorizationService::DELEGATE_NOT_ACTIVE_PARTICIPANT], $items[$rowOutsider->id()->toString()]);
    }
}
