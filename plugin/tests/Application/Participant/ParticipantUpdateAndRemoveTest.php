<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Participant\CancelParticipantCommand;
use StageArt\Application\Participant\ParticipantCancelNotAllowedException;
use StageArt\Application\Participant\ParticipantStatusChangeNotAllowedException;
use StageArt\Application\Participant\ParticipantStatusRequiresConsentException;
use StageArt\Application\Participant\ParticipantTypeConflictException;
use StageArt\Application\Participant\UpdateParticipantCommand;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Application\Shared\UniqueConstraintViolationException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §5.2 / §7.1 (stage 4, N6-N10, T138-T171): PUT and DELETE
 * /participants/{id}.
 */
final class ParticipantUpdateAndRemoveTest extends TestCase
{
    private const NOT_SENT = UpdateParticipantCommand::NOT_SENT;

    private ParticipantConsentFixture $f;
    private Person $member;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->member = $this->f->person('member@example.com');
    }

    /** A row of the member in $status with remarks 'old remark' (and consent when ACTIVE). */
    private function row(string $status, string $type = ParticipantType::CAST): Participant
    {
        $row = $this->f->participantRow($this->member, $status, $type, null, null, $status === ParticipantStatus::ACTIVE);
        $row->changeRemarks('old remark');
        $this->f->participants->save($row);

        return $row;
    }

    /**
     * @param mixed $type
     * @param mixed $status
     * @param mixed $remarks
     */
    private function put(Participant $row, $type = ParticipantType::CAST, $status = self::NOT_SENT, $remarks = self::NOT_SENT): void
    {
        $this->f->updateParticipant()->execute(new UpdateParticipantCommand(
            $row->id()->toString(),
            $this->f->primaryManager->wordPressUserId(),
            $type,
            $status,
            $remarks
        ));
    }

    private function delete(Participant $row): void
    {
        $this->f->cancelParticipant()->execute(new CancelParticipantCommand($row->id()->toString(), $this->f->primaryManager->wordPressUserId()));
    }

    private function stored(Participant $row): Participant
    {
        return $this->f->participants->findById($row->id());
    }

    private function expectRefusal(string $exceptionClass, callable $call): void
    {
        try {
            $call();
            $this->fail("{$exceptionClass} expected.");
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exceptionClass, $e);
        }
    }

    // --- PUT: status (N6, N9) ------------------------------------------

    /** T138 / T149 */
    public function test_active_to_inactive_releases_the_delegate_roles_in_the_same_transaction(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $delegate = $this->f->delegate($this->member);
        $inactive = $this->f->delegate($this->member, RoleKey::REHEARSAL_MANAGER, false);
        $this->assertTrue($this->f->authorization->canManageParticipants($this->member, $this->f->production));
        $this->f->watchProductionLock();

        $this->put($row, ParticipantType::CAST, ParticipantStatus::INACTIVE);

        $this->assertSame(ParticipantStatus::INACTIVE, $this->stored($row)->status()->toString());
        $this->assertFalse($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertFalse($this->f->delegates->findById($inactive->id())->isActive());
        $this->assertFalse($this->f->authorization->canManageParticipants($this->member, $this->f->production), 'T46');
        $this->assertSame([ParticipantEvent::DELEGATE_DEACTIVATED, ParticipantEvent::DEACTIVATED], $this->f->eventStore->types());
        $this->assertSame('PARTICIPANT_DEACTIVATED', $this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED)[0]->detail()['reason']);
        $this->assertSame(['production', 'participant', 'delegate'], $this->f->lockLog->order(), '§5.4');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function refusedChanges(): array
    {
        $cases = [];

        foreach ([ParticipantStatus::DRAFT, ParticipantStatus::PENDING, ParticipantStatus::REJECTED, ParticipantStatus::CANCELLED, ParticipantStatus::WITHDRAWN] as $to) {
            $cases["ACTIVE -> {$to}"] = [ParticipantStatus::ACTIVE, $to, ParticipantStatusChangeNotAllowedException::class];
        }

        $cases['INACTIVE -> ACTIVE'] = [ParticipantStatus::INACTIVE, ParticipantStatus::ACTIVE, ParticipantStatusRequiresConsentException::class];

        $all = [ParticipantStatus::ACTIVE, ParticipantStatus::INACTIVE, ParticipantStatus::DRAFT, ParticipantStatus::PENDING, ParticipantStatus::REJECTED, ParticipantStatus::CANCELLED, ParticipantStatus::WITHDRAWN];

        foreach ([ParticipantStatus::DRAFT, ParticipantStatus::PENDING, ParticipantStatus::REJECTED, ParticipantStatus::CANCELLED, ParticipantStatus::WITHDRAWN] as $from) {
            foreach ($all as $to) {
                if ($to !== $from) {
                    $cases["{$from} -> {$to}"] = [$from, $to, ParticipantStatusChangeNotAllowedException::class];
                }
            }
        }

        $cases['INACTIVE -> DRAFT'] = [ParticipantStatus::INACTIVE, ParticipantStatus::DRAFT, ParticipantStatusChangeNotAllowedException::class];

        return $cases;
    }

    /**
     * T140-T146 / T162: refused, the row (status, type, remarks) unchanged.
     *
     * @dataProvider refusedChanges
     */
    public function test_any_other_status_change_is_refused_and_changes_nothing(string $from, string $to, string $exception): void
    {
        $row = $this->row($from);
        $before = clone $row;
        $delegate = $this->f->delegate($this->member);

        $this->expectRefusal($exception, fn () => $this->put($row, ParticipantType::STAFF, $to, 'new remark'));

        $this->assertEquals($before, $this->stored($row));
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function everyStatus(): array
    {
        $statuses = [ParticipantStatus::ACTIVE, ParticipantStatus::INACTIVE, ParticipantStatus::DRAFT, ParticipantStatus::PENDING, ParticipantStatus::REJECTED, ParticipantStatus::CANCELLED, ParticipantStatus::WITHDRAWN];

        return array_combine($statuses, array_map(static fn (string $status): array => [$status], $statuses));
    }

    /**
     * T139 / T161 / T163: the current status again is an idempotent success -
     * no transition, no delegate change, no status audit; other fields update.
     *
     * @dataProvider everyStatus
     */
    public function test_the_same_status_is_an_idempotent_success(string $status): void
    {
        $row = $this->row($status);
        $delegate = $this->f->delegate($this->member);

        $this->put($row, ParticipantType::CAST, $status, 'new remark');

        $this->assertSame($status, $this->stored($row)->status()->toString());
        $this->assertSame('new remark', $this->stored($row)->remarks());
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
    }

    /** T147 / T164: no status - the status stays whatever it is. */
    public function test_without_status_the_status_is_not_changed(): void
    {
        foreach ([ParticipantStatus::ACTIVE, ParticipantStatus::INACTIVE] as $status) {
            $this->setUp();
            $row = $this->row($status);
            $delegate = $this->f->delegate($this->member);

            $this->put($row, ParticipantType::CAST, self::NOT_SENT, 'new remark');

            $this->assertSame($status, $this->stored($row)->status()->toString());
            $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
            $this->assertSame([], $this->f->eventStore->types());
        }
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function malformedStatuses(): array
    {
        return ['null' => [null], 'empty' => [''], 'unknown' => ['FOO'], 'number' => [1], 'bool' => [true], 'array' => [['ACTIVE']]];
    }

    /**
     * T148: 422 invalid, nothing updated.
     *
     * @dataProvider malformedStatuses
     *
     * @param mixed $status
     */
    public function test_a_malformed_status_is_invalid_and_changes_nothing($status): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $before = clone $row;

        $this->expectRefusal(InvalidArgumentException::class, fn () => $this->put($row, ParticipantType::STAFF, $status, 'new remark'));

        $this->assertEquals($before, $this->stored($row));
    }

    public function test_participant_type_is_required(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);

        foreach ([null, '', 'FOO', 3] as $type) {
            $this->expectRefusal(InvalidArgumentException::class, fn () => $this->put($row, $type));
        }

        $this->assertSame(ParticipantType::CAST, $this->stored($row)->participantType()->toString());
    }

    // --- PUT: participant_type (N7) ------------------------------------

    /**
     * T150 / T151: a type the same subject already has a row of (any
     * status) is 409; the whole PUT rolls back.
     */
    public function test_a_conflicting_type_is_409_and_nothing_changes(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $other = $this->f->participantRow($this->member, ParticipantStatus::WITHDRAWN, ParticipantType::STAFF);
        $delegate = $this->f->delegate($this->member);
        $before = [clone $row, clone $other];

        $this->expectRefusal(ParticipantTypeConflictException::class, fn () => $this->put($row, ParticipantType::STAFF, ParticipantStatus::INACTIVE, 'new remark'));

        $this->assertEquals($before, [$this->stored($row), $this->stored($other)]);
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame(1, $this->f->transactions->rollbacks());
    }

    /** T152 / T153 */
    public function test_a_free_type_is_changed_and_audited_and_the_same_type_is_not(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $delegate = $this->f->delegate($this->member);
        $consentedAt = $row->consentedAt();

        $this->put($row, ParticipantType::CAST);
        $this->assertSame([], $this->f->eventStore->types(), 'Same type: no TYPE_CHANGED');

        $this->put($row, ParticipantType::STAFF);

        $stored = $this->stored($row);
        $this->assertSame(ParticipantType::STAFF, $stored->participantType()->toString());
        $this->assertSame(ParticipantStatus::ACTIVE, $stored->status()->toString());
        $this->assertEquals($consentedAt, $stored->consentedAt());
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([ParticipantEvent::TYPE_CHANGED], $this->f->eventStore->types());
        $this->assertSame(['from_type' => 'CAST', 'to_type' => 'STAFF'], $this->f->eventStore->all()[0]->detail());
    }

    /** T154: a UNIQUE violation on save (a concurrent row) is the same 409, rolled back. */
    public function test_a_unique_violation_on_save_is_409(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $before = clone $row;
        $this->f->participants->failNextSave = new UniqueConstraintViolationException('update', 'wp_stageart_participants', 1062);

        $this->expectRefusal(ParticipantTypeConflictException::class, fn () => $this->put($row, ParticipantType::STAFF, ParticipantStatus::INACTIVE));

        $this->assertEquals($before, $this->stored($row));
        $this->assertSame([], $this->f->eventStore->types());
    }

    /** T155: any other save failure surfaces (500), rolled back. */
    public function test_another_save_failure_surfaces_and_rolls_back(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $before = clone $row;
        $this->f->participants->failNextSave = new PersistenceException('update', 'wp_stageart_participants', 1146);

        $this->expectRefusal(PersistenceException::class, fn () => $this->put($row, ParticipantType::STAFF));

        $this->assertEquals($before, $this->stored($row));
    }

    // --- PUT: remarks (N8) ---------------------------------------------

    /** T156-T159 */
    public function test_remarks_are_kept_cleared_or_set(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);

        $this->put($row);
        $this->assertSame('old remark', $this->stored($row)->remarks(), 'not sent: kept');

        $this->put($row, ParticipantType::CAST, self::NOT_SENT, '  新しい備考  ');
        $this->assertSame('新しい備考', $this->stored($row)->remarks());

        $this->put($row, ParticipantType::CAST, self::NOT_SENT, null);
        $this->assertNull($this->stored($row)->remarks());

        $this->put($row, ParticipantType::CAST, self::NOT_SENT, 'again');
        $this->put($row, ParticipantType::CAST, self::NOT_SENT, '');
        $this->assertNull($this->stored($row)->remarks());
    }

    /**
     * T160
     *
     * @return array<string, array{0: mixed}>
     */
    public static function malformedRemarks(): array
    {
        return ['number' => [12], 'bool' => [false], 'array' => [['a']], 'object' => [(object) ['a' => 1]]];
    }

    /**
     * @dataProvider malformedRemarks
     *
     * @param mixed $remarks
     */
    public function test_malformed_remarks_are_invalid_and_change_nothing($remarks): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $before = clone $row;

        $this->expectRefusal(InvalidArgumentException::class, fn () => $this->put($row, ParticipantType::STAFF, ParticipantStatus::INACTIVE, $remarks));

        $this->assertEquals($before, $this->stored($row));
    }

    public function test_a_failed_delegate_release_rolls_the_deactivation_back(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $delegate = $this->f->delegate($this->member);
        $this->f->delegates->failNextSave = new PersistenceException('update', 'wp_stageart_production_delegates', 1146);

        $this->expectRefusal(PersistenceException::class, fn () => $this->put($row, ParticipantType::CAST, ParticipantStatus::INACTIVE));

        $this->assertSame(ParticipantStatus::ACTIVE, $this->stored($row)->status()->toString());
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
    }

    // --- DELETE (N10) ---------------------------------------------------

    /** T165 / T170 */
    public function test_removing_an_active_member_releases_the_delegate_roles(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $delegate = $this->f->delegate($this->member);
        $this->f->watchProductionLock();

        $this->delete($row);

        $this->assertSame(ParticipantStatus::CANCELLED, $this->stored($row)->status()->toString());
        $this->assertNotNull($this->stored($row), 'The row is kept');
        $this->assertFalse($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertFalse($this->f->authorization->canManageParticipants($this->member, $this->f->production), 'T46');
        $this->assertSame([ParticipantEvent::DELEGATE_DEACTIVATED, ParticipantEvent::REMOVED_BY_MANAGER], $this->f->eventStore->types());
        $this->assertSame('PARTICIPANT_REMOVED', $this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED)[0]->detail()['reason']);
        $this->assertSame(['production', 'participant', 'delegate'], $this->f->lockLog->order());
    }

    /** T166 / T171: an INACTIVE row (pre-R14-B, delegate rows left ACTIVE) - those are released now. */
    public function test_removing_an_inactive_member_releases_rows_left_active(): void
    {
        $row = $this->row(ParticipantStatus::INACTIVE);
        $left = $this->f->delegate($this->member);
        $already = $this->f->delegate($this->member, RoleKey::REHEARSAL_MANAGER, false);
        $alreadyUpdated = $already->updatedAt();

        $this->delete($row);

        $this->assertSame(ParticipantStatus::CANCELLED, $this->stored($row)->status()->toString());
        $this->assertFalse($this->f->delegates->findById($left->id())->isActive());
        $this->assertEquals($alreadyUpdated, $this->f->delegates->findById($already->id())->updatedAt(), 'An INACTIVE row is not touched');
        $this->assertSame([ParticipantEvent::DELEGATE_DEACTIVATED, ParticipantEvent::REMOVED_BY_MANAGER], $this->f->eventStore->types());
        $this->assertSame(ParticipantStatus::INACTIVE, $this->f->eventStore->ofType(ParticipantEvent::REMOVED_BY_MANAGER)[0]->detail()['previous_status']);
    }

    /** T167 */
    public function test_removing_a_cancelled_member_again_has_no_side_effect(): void
    {
        $row = $this->row(ParticipantStatus::CANCELLED);
        $delegate = $this->f->delegate($this->member);
        $before = clone $row;

        $this->delete($row);

        $this->assertEquals($before, $this->stored($row), 'Not saved: updated_at unchanged');
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
    }

    /**
     * T168 / T169
     *
     * @return array<string, array{0: string}>
     */
    public static function notRemovable(): array
    {
        return ['PENDING' => [ParticipantStatus::PENDING], 'DRAFT' => [ParticipantStatus::DRAFT], 'REJECTED' => [ParticipantStatus::REJECTED], 'WITHDRAWN' => [ParticipantStatus::WITHDRAWN]];
    }

    /**
     * @dataProvider notRemovable
     */
    public function test_other_statuses_cannot_be_removed(string $status): void
    {
        $row = $this->row($status);
        $delegate = $this->f->delegate($this->member);
        $before = clone $row;

        $this->expectRefusal(ParticipantCancelNotAllowedException::class, fn () => $this->delete($row));

        $this->assertEquals($before, $this->stored($row));
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
    }

    /** T170: a failed audit write rolls the removal and the release back. */
    public function test_a_failed_audit_write_rolls_the_removal_back(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $delegate = $this->f->delegate($this->member);
        $this->f->eventStore->failNextAppend = new PersistenceException('insert into', 'wp_stageart_participant_events', 1146);

        $this->expectRefusal(PersistenceException::class, fn () => $this->delete($row));

        $this->assertSame(ParticipantStatus::ACTIVE, $this->stored($row)->status()->toString());
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
    }

    /** §5.2: rejoining after removal does not bring the delegate role back (T88). */
    public function test_a_removed_delegate_who_is_invited_again_needs_a_new_appointment(): void
    {
        $row = $this->row(ParticipantStatus::ACTIVE);
        $delegate = $this->f->delegate($this->member);
        $this->delete($row);

        $this->f->invitation('member@example.com');
        $this->f->resolve()->execute($this->member->id(), 'member@example.com');

        $this->assertSame(ParticipantStatus::ACTIVE, $this->stored($row)->status()->toString());
        $this->assertFalse($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertFalse($this->f->authorization->canManageParticipants($this->member, $this->f->production));
    }
}
