<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\Participant\ApproveParticipantRequestCommand;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Participant\ParticipantRequestNotPendingException;
use StageArt\Application\Participant\ParticipantRequestResult;
use StageArt\Application\Participant\RejectParticipantRequestCommand;
use StageArt\Application\Participant\RequestProductionParticipationCommand;
use StageArt\Application\ParticipantInvitation\AcceptParticipantInvitationCommand;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Application\Shared\UniqueConstraintViolationException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\Participant\ParticipantConsentSource;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §4.5 / §4.7 / §5.2 / §5.4 / §7.1 (U13 A+C案, N1): join request,
 * approval and rejection.
 */
final class JoinRequestConsentTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;
    private Person $requester;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->f->production->activate();

        if (! $this->f->production->isPublished()) {
            $this->f->production->publish();
        }
        $this->requester = $this->f->person(self::EMAIL);
    }

    private function request(string $type = ParticipantType::CAST, ?string $joinKeyCode = null): ParticipantRequestResult
    {
        return $this->f->requestParticipation()->execute(new RequestProductionParticipationCommand(
            $this->requester->wordPressUserId(),
            $joinKeyCode === null ? $this->f->production->id()->toString() : null,
            $joinKeyCode,
            $type
        ));
    }

    private function approve(string $participantId): ParticipantRequestResult
    {
        return $this->f->approveRequest()->execute(new ApproveParticipantRequestCommand($this->f->primaryManager->wordPressUserId(), $participantId));
    }

    private function rejectRequest(string $participantId): ParticipantRequestResult
    {
        return $this->f->rejectRequest()->execute(new RejectParticipantRequestCommand($this->f->primaryManager->wordPressUserId(), $participantId));
    }

    private function joinKey(?int $maxUses = null): JoinKey
    {
        $joinKey = JoinKey::issueForProduction($this->f->production->id()->toString(), $this->f->primaryManager->id(), null, $maxUses);
        $this->f->joinKeys->save($joinKey);

        return $joinKey;
    }

    // --- request -----------------------------------------------------

    public function test_a_new_request_is_a_pending_join_request_row_with_its_request_time(): void
    {
        $before = new DateTimeImmutable();
        $this->f->watchProductionLock();

        $result = $this->request(ParticipantType::CAST, $this->joinKey()->code());

        $participant = $this->f->participants->findById(ParticipantId::fromString($result->id));
        $this->assertSame(ParticipantStatus::PENDING, $participant->status()->toString());
        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString());
        $this->assertGreaterThanOrEqual($before, $participant->requestedAt());
        $this->assertNull($participant->consentedAt(), 'Consent is recorded on approval');
        $this->assertSame([ParticipantEvent::JOIN_REQUESTED], $this->f->eventStore->types());
        $this->assertNull($this->f->eventStore->all()[0]->detail()['previous_status']);
        $this->assertSame(['production', 'participant', 'join_key'], $this->f->lockLog->order(), '§5.4 lock order');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reusableStates(): array
    {
        return [
            'CANCELLED' => [ParticipantStatus::CANCELLED],
            'REJECTED' => [ParticipantStatus::REJECTED],
            'WITHDRAWN' => [ParticipantStatus::WITHDRAWN],
            'INACTIVE' => [ParticipantStatus::INACTIVE],
        ];
    }

    /**
     * @dataProvider reusableStates
     */
    public function test_a_re_request_reuses_the_row(string $status): void
    {
        $existing = $this->f->participantRow($this->requester, $status, ParticipantType::CAST, ParticipantEnrollmentSource::INVITATION, new DateTimeImmutable('2026-01-01T00:00:00+00:00'), $status === ParticipantStatus::INACTIVE);
        $consentedAt = $existing->consentedAt();

        $result = $this->request();

        $this->assertSame($existing->id()->toString(), $result->id, 'Participant id kept');
        $participant = $this->f->participants->findById($existing->id());
        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertSame(ParticipantStatus::PENDING, $participant->status()->toString());
        $this->assertSame(ParticipantEnrollmentSource::INVITATION, $participant->enrollmentSource()->toString(), 'enrollmentSource kept');
        $this->assertGreaterThan(new DateTimeImmutable('2026-06-01T00:00:00+00:00'), $participant->requestedAt(), 'requestedAt is this request');
        $this->assertEquals($consentedAt, $participant->consentedAt(), 'Consent is not changed by a request');
        $this->assertSame($participant->requestedAt()->format(DATE_ATOM), $result->requestedAt);
        $detail = $this->f->eventStore->all()[0]->detail();
        $this->assertSame($status, $detail['previous_status']);
        $this->assertTrue($detail['reused_participant']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function refusedStates(): array
    {
        return [
            'ACTIVE' => [ParticipantStatus::ACTIVE],
            'PENDING' => [ParticipantStatus::PENDING],
            'DRAFT (N1)' => [ParticipantStatus::DRAFT],
        ];
    }

    /**
     * @dataProvider refusedStates
     */
    public function test_an_active_pending_or_draft_row_is_409_and_nothing_changes(string $status): void
    {
        $existing = $this->f->participantRow($this->requester, $status);
        $joinKey = $this->joinKey();

        try {
            $this->request(ParticipantType::CAST, $joinKey->code());
            $this->fail('409 expected.');
        } catch (ParticipantAlreadyExistsException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($status, $this->f->participants->findById($existing->id())->status()->toString());
        $this->assertSame(0, $this->f->joinKeys->findById($joinKey->id())->useCount(), 'No use counted');
        $this->assertSame([], $this->f->eventStore->types());
    }

    public function test_two_requests_never_make_two_rows(): void
    {
        $first = $this->request();

        try {
            $this->request();
            $this->fail('The second request must be refused.');
        } catch (ParticipantAlreadyExistsException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertSame($first->id, $this->f->participants->findByProductionId($this->f->production->id())[0]->id()->toString());
    }

    public function test_the_join_key_use_is_counted_only_when_the_request_is_stored(): void
    {
        $joinKey = $this->joinKey();
        $this->f->participants->failNextSave = new PersistenceException('insert into', 'wp_stageart_participants', 1146);

        try {
            $this->request(ParticipantType::CAST, $joinKey->code());
            $this->fail('A failed save must not succeed.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, $this->f->joinKeys->findById($joinKey->id())->useCount());
        $this->assertSame([], $this->f->participants->findByProductionId($this->f->production->id()));

        $this->request(ParticipantType::CAST, $joinKey->code());
        $this->assertSame(1, $this->f->joinKeys->findById($joinKey->id())->useCount());
    }

    /** §7.1: a key used up between the check and the transaction rolls the whole request back (404). */
    public function test_a_key_that_can_no_longer_be_used_rolls_the_request_back(): void
    {
        $joinKey = $this->joinKey(1);
        $reused = $this->f->participantRow($this->requester, ParticipantStatus::WITHDRAWN);
        $this->f->productions->beforeLockGranted = function () use ($joinKey): void {
            $this->f->joinKeys->findById($joinKey->id())->recordUse();
        };

        try {
            $this->request(ParticipantType::CAST, $joinKey->code());
            $this->fail('404 expected.');
        } catch (JoinKeyNotFoundException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ParticipantStatus::WITHDRAWN, $this->f->participants->findById($reused->id())->status()->toString(), 'Reuse rolled back');
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame(1, $this->f->transactions->rollbacks());
    }

    public function test_a_failed_join_key_write_rolls_the_request_back(): void
    {
        $joinKey = $this->joinKey();
        $this->f->joinKeys->failNextUse = new PersistenceException('update', 'wp_stageart_join_keys', 1205);

        try {
            $this->request(ParticipantType::CAST, $joinKey->code());
            $this->fail('The failure must surface.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([], $this->f->participants->findByProductionId($this->f->production->id()));
    }

    public function test_the_last_use_exhausts_the_key_as_before(): void
    {
        $joinKey = $this->joinKey(1);

        $this->request(ParticipantType::CAST, $joinKey->code());

        $this->assertSame(JoinKey::STATUS_EXHAUSTED, $this->f->joinKeys->findById($joinKey->id())->status());
    }

    public function test_a_unique_violation_is_never_reported_as_success(): void
    {
        $this->f->participants->failNextSave = new UniqueConstraintViolationException('insert into', 'wp_stageart_participants');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->request();
    }

    // --- approval / rejection ----------------------------------------

    public function test_approval_records_consent_at_the_request_time(): void
    {
        $request = $this->request();
        $requestedAt = $this->f->participants->findById(ParticipantId::fromString($request->id))->requestedAt();
        $dormant = $this->f->delegate($this->requester);
        $this->f->watchProductionLock();

        $approved = $this->approve($request->id);

        $participant = $this->f->participants->findById(ParticipantId::fromString($request->id));
        $this->assertSame(ParticipantStatus::ACTIVE, $approved->status);
        $this->assertSame(ParticipantConsentSource::JOIN_REQUEST, $participant->consentSource()->toString());
        $this->assertEquals($requestedAt, $participant->consentedAt(), 'The request time, not the approval time');
        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString());
        $this->assertFalse($this->f->delegates->findById($dormant->id())->isActive(), 'DORMANT_ON_ENROLLMENT');
        $this->assertSame([ParticipantEvent::JOIN_REQUESTED, ParticipantEvent::DELEGATE_DEACTIVATED, ParticipantEvent::JOIN_APPROVED], $this->f->eventStore->types());
        $this->assertTrue($this->f->eventStore->ofType(ParticipantEvent::JOIN_APPROVED)[0]->detail()['consent_recorded']);
        $this->assertSame(['production', 'participant', 'delegate'], $this->f->lockLog->order());
    }

    public function test_approving_a_pre_r14b_request_records_no_consent(): void
    {
        $legacy = $this->f->participantRow($this->requester, ParticipantStatus::PENDING, ParticipantType::CAST, null, null);

        $this->approve($legacy->id()->toString());

        $participant = $this->f->participants->findById($legacy->id());
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertNull($participant->consentSource());
        $this->assertNull($participant->consentedAt());
        $this->assertFalse($this->f->eventStore->ofType(ParticipantEvent::JOIN_APPROVED)[0]->detail()['consent_recorded']);
    }

    public function test_a_second_approval_or_rejection_is_409(): void
    {
        $request = $this->request();
        $this->approve($request->id);

        foreach ([fn () => $this->approve($request->id), fn () => $this->rejectRequest($request->id)] as $again) {
            try {
                $again();
                $this->fail('409 not_pending expected.');
            } catch (ParticipantRequestNotPendingException $e) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertCount(1, $this->f->eventStore->ofType(ParticipantEvent::JOIN_APPROVED));
    }

    public function test_rejection_makes_it_rejected_and_is_recorded(): void
    {
        $request = $this->request();

        $rejected = $this->rejectRequest($request->id);

        $this->assertSame(ParticipantStatus::REJECTED, $rejected->status);
        $this->assertSame([ParticipantEvent::JOIN_REQUESTED, ParticipantEvent::JOIN_REJECTED], $this->f->eventStore->types());
        $this->assertSame([], $this->f->notificationTypes(), 'The design defines no notification here');
    }

    public function test_only_a_manager_can_decide(): void
    {
        $request = $this->request();

        $this->expectException(ParticipantAccessDeniedException::class);
        $this->f->approveRequest()->execute(new ApproveParticipantRequestCommand($this->requester->wordPressUserId(), $request->id));
    }

    // --- §4.7: with an invitation acceptance --------------------------

    private function acceptInvitation(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->accept()->execute(new AcceptParticipantInvitationCommand($this->requester->wordPressUserId(), $invitation->id()->toString()));
    }

    public function test_acceptance_first_then_approval_is_409(): void
    {
        $request = $this->request();
        $this->acceptInvitation();

        $this->expectException(ParticipantRequestNotPendingException::class);
        $this->approve($request->id);
    }

    public function test_approval_first_then_acceptance_records_consent_on_the_same_row(): void
    {
        $request = $this->request();
        $this->approve($request->id);

        $this->acceptInvitation();

        $participant = $this->f->participants->findById(ParticipantId::fromString($request->id));
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString(), 'The latest consent');
        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertCount(1, $this->f->eventStore->ofType(ParticipantEvent::CONSENT_RECORDED));
    }

    public function test_rejection_first_then_acceptance_brings_the_same_row_back(): void
    {
        $request = $this->request();
        $this->rejectRequest($request->id);

        $this->acceptInvitation();

        $participant = $this->f->participants->findById(ParticipantId::fromString($request->id));
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantStatus::REJECTED, $this->f->eventStore->ofType(ParticipantEvent::ACCEPTED)[0]->detail()['previous_status']);
    }

    public function test_rejection_after_acceptance_is_409(): void
    {
        $request = $this->request();
        $this->acceptInvitation();

        $this->expectException(ParticipantRequestNotPendingException::class);
        $this->rejectRequest($request->id);
    }
}
