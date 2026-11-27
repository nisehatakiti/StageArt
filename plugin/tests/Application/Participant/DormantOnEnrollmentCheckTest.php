<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Participant\ApproveParticipantRequestCommand;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\Person;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §4.6 DORMANT_ON_ENROLLMENT (review fix): whether the Person already
 * holds an ACTIVE PERSON row of another type is asked with a dedicated,
 * locking, failure-checked Repository read
 * (existsActivePersonParticipantOfAnotherTypeForUpdate()). A failed read
 * throws and the whole registration from an invitation (invitation policy
 * 2026-11-27; the R14-B acceptance before) / approval rolls back - it is
 * never taken for "none", which would release delegate roles that must be
 * kept.
 */
final class DormantOnEnrollmentCheckTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;
    private Person $person;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->f->production->activate();

        if (! $this->f->production->isPublished()) {
            $this->f->production->publish();
        }
        $this->person = $this->f->person(self::EMAIL);
    }

    /** The invitation policy's registration once the address is verified (the R14-B acceptance before). */
    private function accept(ParticipantInvitation $invitation): void
    {
        $this->f->resolve()->execute($this->person->id(), $invitation->email());
    }

    private function approve(Participant $request): void
    {
        $this->f->approveRequest()->execute(new ApproveParticipantRequestCommand($this->f->primaryManager->wordPressUserId(), $request->id()->toString()));
    }

    private function pendingRequest(string $type = ParticipantType::CAST): Participant
    {
        return $this->f->participantRow($this->person, ParticipantStatus::PENDING, $type, ParticipantEnrollmentSource::JOIN_REQUEST, new DateTimeImmutable('-1 day'));
    }

    /** A NAME_ONLY row whose subject_id happens to be the Person's id - still not the Person's participation. */
    private function nameOnlyRow(string $type): Participant
    {
        $row = Participant::reconstitute(
            ParticipantId::generate(),
            $this->f->production->id(),
            ParticipantSubjectType::nameOnly(),
            $this->person->id()->toString(),
            ParticipantType::fromString($type),
            ParticipantStatus::active(),
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            null,
            '名前だけの登録',
            ParticipantEnrollmentSource::managerRecord()
        );
        $this->f->participants->save($row);

        return $row;
    }

    private function assertKept(ProductionDelegate $delegate): void
    {
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive(), 'The delegate role is kept');
        $this->assertSame([], $this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED));
    }

    private function assertReleased(ProductionDelegate $delegate): void
    {
        $this->assertFalse($this->f->delegates->findById($delegate->id())->isActive(), 'DORMANT_ON_ENROLLMENT');
        $released = $this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED);
        $this->assertCount(1, $released);
        $this->assertSame('DORMANT_ON_ENROLLMENT', $released[0]->detail()['reason']);
    }

    // --- another type ACTIVE: kept -----------------------------------

    public function test_acceptance_keeps_the_roles_of_a_person_active_in_another_type(): void
    {
        $this->f->participantRow($this->person, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $this->f->participantRow($this->person, ParticipantStatus::WITHDRAWN, ParticipantType::CAST);
        $delegate = $this->f->delegate($this->person);

        $this->accept($this->f->invitation(self::EMAIL, null, ParticipantType::CAST));

        $this->assertKept($delegate);
        $this->assertSame(1, $this->f->participants->otherTypeChecks);
    }

    public function test_approval_keeps_the_roles_of_a_person_active_in_another_type(): void
    {
        $this->f->participantRow($this->person, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $request = $this->pendingRequest(ParticipantType::CAST);
        $delegate = $this->f->delegate($this->person);

        $this->approve($request);

        $this->assertSame(ParticipantStatus::ACTIVE, $this->f->participants->findById($request->id())->status()->toString());
        $this->assertKept($delegate);
        $this->assertSame(1, $this->f->participants->otherTypeChecks);
    }

    // --- no other type ACTIVE: released ------------------------------

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function otherTypeStates(): array
    {
        return [
            'no row of the other type' => [null],
            'other type WITHDRAWN' => [ParticipantStatus::WITHDRAWN],
            'other type PENDING' => [ParticipantStatus::PENDING],
            'other type DRAFT' => [ParticipantStatus::DRAFT],
            'other type INACTIVE' => [ParticipantStatus::INACTIVE],
        ];
    }

    /**
     * @dataProvider otherTypeStates
     */
    public function test_acceptance_releases_the_roles_when_no_other_type_is_active(?string $otherTypeStatus): void
    {
        if ($otherTypeStatus !== null) {
            $this->f->participantRow($this->person, $otherTypeStatus, ParticipantType::STAFF);
        }
        $delegate = $this->f->delegate($this->person);

        $this->accept($this->f->invitation(self::EMAIL, null, ParticipantType::CAST));

        $this->assertReleased($delegate);
    }

    public function test_approval_releases_the_roles_when_no_other_type_is_active(): void
    {
        $this->f->participantRow($this->person, ParticipantStatus::REJECTED, ParticipantType::STAFF);
        $request = $this->pendingRequest(ParticipantType::CAST);
        $delegate = $this->f->delegate($this->person);

        $this->approve($request);

        $this->assertReleased($delegate);
    }

    // --- what never counts as "another type" -------------------------

    public function test_the_type_being_enrolled_never_counts_as_another_type(): void
    {
        $active = $this->f->participantRow($this->person, ParticipantStatus::ACTIVE, ParticipantType::CAST);

        $this->assertTrue(
            $this->f->enrollment()->personBecomesParticipantWith($active),
            'An ACTIVE row of the same type is not "ACTIVE in another type"'
        );
        $this->assertFalse($this->f->participants->existsActivePersonParticipantOfAnotherTypeForUpdate(
            $this->f->production->id(),
            $this->person->id()->toString(),
            ParticipantType::cast()
        ));
        $this->assertTrue($this->f->participants->existsActivePersonParticipantOfAnotherTypeForUpdate(
            $this->f->production->id(),
            $this->person->id()->toString(),
            ParticipantType::staff()
        ));
    }

    public function test_an_active_name_only_row_is_not_an_active_person_participant(): void
    {
        $this->nameOnlyRow(ParticipantType::STAFF);
        $delegate = $this->f->delegate($this->person);

        $this->accept($this->f->invitation(self::EMAIL, null, ParticipantType::CAST));

        $this->assertReleased($delegate);
    }

    public function test_an_active_row_of_another_person_or_production_does_not_count(): void
    {
        $someoneElse = $this->f->person('hanako@example.com');
        $this->f->participantRow($someoneElse, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $delegate = $this->f->delegate($this->person);

        $this->accept($this->f->invitation(self::EMAIL, null, ParticipantType::CAST));

        $this->assertReleased($delegate);
    }

    // --- U15: already ACTIVE -------------------------------------------

    public function test_an_already_active_participant_keeps_the_roles_without_asking(): void
    {
        $this->f->participantRow($this->person, ParticipantStatus::ACTIVE, ParticipantType::CAST);
        $delegate = $this->f->delegate($this->person);
        $this->f->participants->failNextOtherTypeCheck = new PersistenceException('read from', 'wp_stageart_participants', 1205);

        $this->accept($this->f->invitation(self::EMAIL, null, ParticipantType::CAST));

        $this->assertKept($delegate);
        $this->assertSame(0, $this->f->participants->otherTypeChecks, 'U15: nothing to decide, nothing read');
        $this->assertSame([], $this->f->eventStore->types(), 'Nothing changed on the row (no consent is recorded any more)');
    }

    // --- a failed read rolls everything back ---------------------------

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function acceptedRowStates(): array
    {
        return [
            'new row' => [null],
            'reused WITHDRAWN row' => [ParticipantStatus::WITHDRAWN],
            'pending join request' => [ParticipantStatus::PENDING],
        ];
    }

    /**
     * @dataProvider acceptedRowStates
     */
    public function test_a_failed_check_rolls_the_acceptance_back(?string $existingStatus): void
    {
        $existing = $existingStatus !== null
            ? $this->f->participantRow($this->person, $existingStatus, ParticipantType::CAST)
            : null;
        $staff = $this->f->participantRow($this->person, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $delegate = $this->f->delegate($this->person);
        $invitation = $this->f->invitation(self::EMAIL, null, ParticipantType::CAST);
        $before = [
            'invitation' => clone $invitation,
            'participants' => $this->f->participants->snapshot(),
            'delegate' => clone $delegate,
        ];
        $this->f->participants->failNextOtherTypeCheck = new PersistenceException('read from', 'wp_stageart_participants', 1205);

        // The registration runs after a verification: a failure there is
        // logged (ids only) and the invitation stays PENDING - never read as
        // "none" (which would release the roles).
        $log = ini_get('error_log');
        $file = tempnam(sys_get_temp_dir(), 'dormant');
        ini_set('error_log', $file);

        try {
            $this->accept($invitation);
        } finally {
            ini_set('error_log', (string) $log);
        }

        $logged = (string) file_get_contents($file);
        @unlink($file);
        $this->assertStringContainsString('PersistenceException', $logged);
        $this->assertStringNotContainsString(self::EMAIL, $logged);

        $this->assertEquals($before['invitation'], $this->f->invitations->findById($invitation->id()));
        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
        $this->assertEquals($before['participants'], $this->f->participants->snapshot(), 'No Participant row created or changed');
        $this->assertCount($existing !== null ? 2 : 1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertEquals($before['delegate'], $this->f->delegates->findById($delegate->id()), 'The delegate role is not released');
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame(ParticipantStatus::ACTIVE, $this->f->participants->findById($staff->id())->status()->toString());
        $this->assertSame([], $this->f->eventStore->types(), 'No audit event');
        $this->assertSame([], $this->f->notificationTypes(), 'No notification');
        $this->assertSame(1, $this->f->transactions->rollbacks());
        $this->assertSame(0, $this->f->transactions->commits());
    }

    public function test_a_failed_check_rolls_the_approval_back(): void
    {
        $request = $this->pendingRequest(ParticipantType::CAST);
        $delegate = $this->f->delegate($this->person);
        $before = ['participants' => $this->f->participants->snapshot(), 'delegate' => clone $delegate];
        $this->f->participants->failNextOtherTypeCheck = new PersistenceException('read from', 'wp_stageart_participants', 1205);

        try {
            $this->approve($request);
            $this->fail('A failed read must surface, not read as "none".');
        } catch (PersistenceException $e) {
            $this->assertSame(1205, $e->mysqlErrno());
        }

        $this->assertEquals($before['participants'], $this->f->participants->snapshot());
        $this->assertSame(ParticipantStatus::PENDING, $this->f->participants->findById($request->id())->status()->toString());
        $this->assertNull($this->f->participants->findById($request->id())->consentedAt());
        $this->assertEquals($before['delegate'], $this->f->delegates->findById($delegate->id()));
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame([], $this->f->notificationTypes());
        $this->assertSame(1, $this->f->transactions->rollbacks());
        $this->assertSame(0, $this->f->transactions->commits());
    }
}
