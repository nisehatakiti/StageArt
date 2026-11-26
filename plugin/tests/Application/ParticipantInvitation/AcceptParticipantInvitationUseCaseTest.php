<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Application\Participant\ParticipantConsentNotifier;
use StageArt\Application\Participant\ParticipantDraftExistsException;
use StageArt\Application\ParticipantInvitation\AcceptParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\AcceptParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\InvitationRecipientNotEligibleException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationClosedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationExpiredException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotPendingException;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\ParticipantConsentSource;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §5.1 / §5.2 / §7.2 accept (stage 3 Application).
 */
final class AcceptParticipantInvitationUseCaseTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;
    private Person $invitee;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->invitee = $this->f->person(self::EMAIL);
    }

    private function accept(ParticipantInvitation $invitation, ?Person $as = null): \StageArt\Application\ParticipantInvitation\AcceptParticipantInvitationResult
    {
        return $this->f->accept()->execute(new AcceptParticipantInvitationCommand(
            ($as ?? $this->invitee)->wordPressUserId(),
            $invitation->id()->toString()
        ));
    }

    private function participantOf(Person $person, string $type = ParticipantType::CAST): ?\StageArt\Domain\Participant\Participant
    {
        return $this->f->participants->findByProductionAndSubject(
            $this->f->production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::fromString($type)
        );
    }

    private function assertNothingLeaked(ParticipantInvitation $invitation): void
    {
        $recorded = $this->f->everythingRecordedAndSent();
        $audit = implode("\n", array_map(static fn (ParticipantEvent $e): string => (string) json_encode($e->detail()), $this->f->eventStore->all()));

        $this->assertStringNotContainsString(self::EMAIL, $recorded);
        $this->assertStringNotContainsString($invitation->tokenHash(), $recorded);
        $this->assertStringNotContainsString('internal memo', $recorded);
        $this->assertStringNotContainsString('招待 太郎', $audit, 'invited_name never goes into the audit log');
    }

    public function test_a_new_participant_is_created_active_with_its_route_and_consent(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);

        $result = $this->accept($invitation);

        $participant = $this->participantOf($this->invitee);
        $this->assertSame($participant->id()->toString(), $result->participantId);
        $this->assertSame(ParticipantStatus::ACTIVE, $result->status);
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantEnrollmentSource::INVITATION, $participant->enrollmentSource()->toString());
        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString());
        $this->assertNotNull($participant->consentedAt());
        $this->assertSame('招待 太郎', $participant->displayName(), 'display_name is the name the manager typed');

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationStatus::ACCEPTED, $stored->status()->toString());
        $this->assertTrue($stored->inviteePersonId()->equals($this->invitee->id()));
        $this->assertTrue($stored->participantId()->equals($participant->id()));
        $this->assertNotNull($stored->respondedAt());

        $this->assertSame([ParticipantEvent::ACCEPTED], $this->f->eventStore->types());
        $accepted = $this->f->eventStore->ofType(ParticipantEvent::ACCEPTED)[0];
        $this->assertNull($accepted->detail()['previous_status']);
        $this->assertFalse($accepted->detail()['reused_participant']);
        $this->assertSame([ParticipantConsentNotifier::TYPE_INVITATION_ACCEPTED], $this->f->notificationTypes());
        $this->assertTrue($this->f->dispatcher->dispatched()[0]['personId']->equals($this->f->primaryManager->id()));
        $this->assertStringContainsString('招待 太郎さん', $this->f->dispatcher->dispatched()[0]['payload']['message']);
        $this->assertSame(1, $this->f->transactions->commits());
        $this->assertSame(0, $this->f->invitations->tokenHashLookups, 'No token lookup');
        $this->assertNothingLeaked($invitation);
    }

    /** U15: an already ACTIVE Participant only records consent; its ACTIVE delegate rows stay. */
    public function test_an_active_participant_only_records_consent(): void
    {
        $existing = $this->f->participantRow($this->invitee, ParticipantStatus::ACTIVE);
        $delegate = $this->f->delegate($this->invitee);
        $invitation = $this->f->invitation(self::EMAIL);

        $result = $this->accept($invitation);

        $participant = $this->participantOf($this->invitee);
        $this->assertSame($existing->id()->toString(), $result->participantId);
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertNull($participant->enrollmentSource(), 'The original (pre-R14-B) route is kept');
        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString());
        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive(), 'U15: not deactivated');
        $this->assertSame([ParticipantEvent::ACCEPTED, ParticipantEvent::CONSENT_RECORDED], $this->f->eventStore->types());
        $this->assertSame(ParticipantStatus::ACTIVE, $this->f->eventStore->ofType(ParticipantEvent::ACCEPTED)[0]->detail()['previous_status']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reusableStates(): array
    {
        return [
            'CANCELLED' => [ParticipantStatus::CANCELLED],
            'WITHDRAWN' => [ParticipantStatus::WITHDRAWN],
            'REJECTED' => [ParticipantStatus::REJECTED],
            'INACTIVE' => [ParticipantStatus::INACTIVE],
        ];
    }

    /**
     * @dataProvider reusableStates
     */
    public function test_the_same_row_is_reused_and_old_appointments_stay_dormant(string $status): void
    {
        $existing = $this->f->participantRow($this->invitee, $status, ParticipantType::CAST, ParticipantEnrollmentSource::JOIN_REQUEST);
        $leftActive = $this->f->delegate($this->invitee);
        $alreadyInactive = $this->f->delegate($this->invitee, 'REHEARSAL_MANAGER', false);
        $this->f->watchProductionLock();

        $result = $this->accept($this->f->invitation(self::EMAIL));

        $participant = $this->participantOf($this->invitee);
        $this->assertSame($existing->id()->toString(), $result->participantId, 'No new Participant id for the same key');
        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString(), 'enrollmentSource is never changed');
        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString());

        $this->assertFalse($this->f->delegates->findById($leftActive->id())->isActive(), 'DORMANT_ON_ENROLLMENT');
        $this->assertTrue($this->f->delegates->findById($leftActive->id())->updatedBy()->equals($this->invitee->id()));
        $this->assertFalse($this->f->delegates->findById($alreadyInactive->id())->isActive(), 'Never turned back to ACTIVE');
        $this->assertNotNull($this->f->delegates->findById($leftActive->id()), 'Never deleted');

        $this->assertSame([ParticipantEvent::DELEGATE_DEACTIVATED, ParticipantEvent::ACCEPTED], $this->f->eventStore->types());
        $this->assertSame('DORMANT_ON_ENROLLMENT', $this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED)[0]->detail()['reason']);
        $accepted = $this->f->eventStore->ofType(ParticipantEvent::ACCEPTED)[0]->detail();
        $this->assertSame($status, $accepted['previous_status']);
        $this->assertTrue($accepted['reused_participant']);
        $this->assertSame(['production', 'invitation', 'participant', 'delegate'], $this->f->lockLog->order(), '§5.4 lock order');
    }

    /** §4.7: a pending join request is taken over by the acceptance (same row). */
    public function test_a_pending_join_request_row_becomes_active(): void
    {
        $request = $this->f->participantRow($this->invitee, ParticipantStatus::PENDING, ParticipantType::CAST, ParticipantEnrollmentSource::JOIN_REQUEST, new DateTimeImmutable('-1 day'));

        $result = $this->accept($this->f->invitation(self::EMAIL));

        $participant = $this->participantOf($this->invitee);
        $this->assertSame($request->id()->toString(), $result->participantId);
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString());
        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString());
        $detail = $this->f->eventStore->ofType(ParticipantEvent::ACCEPTED)[0]->detail();
        $this->assertSame(ParticipantStatus::PENDING, $detail['previous_status']);
        $this->assertTrue($detail['superseded_join_request']);
    }

    /** N11: a DRAFT row of the same type refuses the acceptance and nothing changes. */
    public function test_a_draft_of_the_same_type_is_409_and_the_invitation_stays_pending(): void
    {
        $draft = $this->f->participantRow($this->invitee, ParticipantStatus::DRAFT);
        $invitation = $this->f->invitation(self::EMAIL);
        $before = clone $invitation;

        try {
            $this->accept($invitation);
            $this->fail('A DRAFT row must refuse the acceptance.');
        } catch (ParticipantDraftExistsException $e) {
            $this->addToAssertionCount(1);
        }

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationStatus::PENDING, $stored->status()->toString());
        $this->assertNull($stored->respondedAt());
        $this->assertEquals($before->updatedAt(), $stored->updatedAt());
        $this->assertSame(ParticipantStatus::DRAFT, $this->f->participants->findById($draft->id())->status()->toString());
        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame([], $this->f->notificationTypes());
        $this->assertSame(1, $this->f->transactions->rollbacks());
    }

    public function test_a_draft_of_another_type_does_not_block(): void
    {
        $this->f->participantRow($this->invitee, ParticipantStatus::DRAFT, ParticipantType::STAFF);

        $this->accept($this->f->invitation(self::EMAIL, null, ParticipantType::CAST));

        $this->assertSame(ParticipantStatus::ACTIVE, $this->participantOf($this->invitee, ParticipantType::CAST)->status()->toString());
        $this->assertSame(ParticipantStatus::DRAFT, $this->participantOf($this->invitee, ParticipantType::STAFF)->status()->toString());
    }

    /** A Person already ACTIVE in the other type was a participant already: their delegate rows are not dormant. */
    public function test_a_person_active_in_the_other_type_keeps_their_appointments(): void
    {
        $this->f->participantRow($this->invitee, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $delegate = $this->f->delegate($this->invitee);

        $this->accept($this->f->invitation(self::EMAIL, null, ParticipantType::CAST));

        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED));
    }

    public function test_a_second_acceptance_returns_the_first_result_without_side_effects(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $first = $this->accept($invitation);
        $respondedAt = $this->f->invitations->findById($invitation->id())->respondedAt();

        $second = $this->accept($invitation);

        $this->assertSame($first->participantId, $second->participantId);
        $this->assertEquals($respondedAt, $this->f->invitations->findById($invitation->id())->respondedAt());
        $this->assertSame([ParticipantEvent::ACCEPTED], $this->f->eventStore->types(), 'One audit event');
        $this->assertSame([ParticipantConsentNotifier::TYPE_INVITATION_ACCEPTED], $this->f->notificationTypes(), 'One notification');
    }

    /** D5: the closing is committed although 409 is returned; no notification for PRODUCTION_CLOSED. */
    public function test_a_closed_production_closes_the_invitation_and_commits_it(): void
    {
        $this->f->forceProductionStatus(ProductionStatus::COMPLETED);
        $invitation = $this->f->invitation(self::EMAIL);

        try {
            $this->accept($invitation);
            $this->fail('409 closed expected.');
        } catch (ParticipantInvitationClosedException $e) {
            $this->assertSame('PRODUCTION_CLOSED', $e->closeReason());
        }

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationStatus::CANCELLED, $stored->status()->toString());
        $this->assertSame(ParticipantInvitationCloseReason::PRODUCTION_CLOSED, $stored->closeReason()->toString());
        $this->assertNotNull($stored->closedAt());
        $this->assertNull($stored->closedByPersonId());
        $this->assertNull($this->participantOf($this->invitee));
        $this->assertSame(1, $this->f->transactions->commits());
        $this->assertSame([ParticipantEvent::INVITATION_CLOSED], $this->f->eventStore->types());
        $this->assertFalse($this->f->eventStore->all()[0]->detail()['primary_manager_notify_attempted']);
        $this->assertSame([], $this->f->notificationTypes());
        $this->assertNull($this->f->invitations->revocationNotifiedAt($invitation->id()));
    }

    /** D3 / U10: INVITER_REVOKED is committed and the PrimaryManager is told once. */
    public function test_an_inviter_without_permission_closes_the_invitation_and_notifies_once(): void
    {
        $manager = $this->f->participantManager();
        $invitation = $this->f->invitation(self::EMAIL, $manager);

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $row) {
            $row->deactivate($this->f->primaryManager->id());
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->accept($invitation);
                $this->fail('409 closed expected.');
            } catch (ParticipantInvitationClosedException $e) {
                $this->assertSame('INVITER_REVOKED', $e->closeReason());
            }
        }

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationCloseReason::INVITER_REVOKED, $stored->closeReason()->toString());
        $this->assertNotNull($this->f->invitations->revocationNotifiedAt($invitation->id()));
        $this->assertNull($this->participantOf($this->invitee));
        $this->assertSame([ParticipantConsentNotifier::TYPE_INVITATION_CLOSED], $this->f->notificationTypes(), 'Attempted once');
        $this->assertTrue($this->f->dispatcher->dispatched()[0]['personId']->equals($this->f->primaryManager->id()));
        $this->assertSame([ParticipantEvent::INVITATION_CLOSED], $this->f->eventStore->types(), 'Not recorded twice');
        $this->assertTrue($this->f->eventStore->all()[0]->detail()['primary_manager_notify_attempted']);
        $this->assertSame('ACCEPT', $this->f->eventStore->all()[0]->detail()['detected_by']);
    }

    public function test_an_inviter_who_still_manages_participants_can_be_accepted(): void
    {
        $manager = $this->f->participantManager();

        $this->accept($this->f->invitation(self::EMAIL, $manager));

        $this->assertTrue($this->f->dispatcher->dispatched()[0]['personId']->equals($manager->id()));
    }

    public function test_a_failed_participant_write_rolls_everything_back(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->participants->failNextSave = new PersistenceException('insert into', 'wp_stageart_participants', 1146);

        try {
            $this->accept($invitation);
            $this->fail('The failure must surface.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
        $this->assertNull($this->participantOf($this->invitee));
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame([], $this->f->notificationTypes());
        $this->assertSame(1, $this->f->transactions->rollbacks());
    }

    public function test_a_failed_audit_write_rolls_the_acceptance_back(): void
    {
        $existing = $this->f->participantRow($this->invitee, ParticipantStatus::WITHDRAWN);
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->eventStore->failNextAppend = new PersistenceException('insert into', 'wp_stageart_participant_events', 1146);

        try {
            $this->accept($invitation);
            $this->fail('The failure must surface.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
        $this->assertSame(ParticipantStatus::WITHDRAWN, $this->f->participants->findById($existing->id())->status()->toString());
        $this->assertSame([], $this->f->notificationTypes());
    }

    public function test_a_failed_notification_does_not_undo_the_acceptance(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $failing = new class () implements NotificationDispatcherInterface {
            public function dispatch(PersonId $personId, string $type, array $payload): void
            {
                throw new RuntimeException('mail server down for taro@example.com');
            }
        };
        $useCase = new AcceptParticipantInvitationUseCase(
            $this->f->invitations,
            $this->f->productions,
            $this->f->verifier(),
            $this->f->conditions(),
            $this->f->enrollment(),
            $this->f->releaser(),
            $this->f->events,
            new ParticipantConsentNotifier($failing),
            $this->f->transactions
        );

        $log = ini_get('error_log');
        $file = tempnam(sys_get_temp_dir(), 'r14b');
        ini_set('error_log', $file);

        try {
            $result = $useCase->execute(new AcceptParticipantInvitationCommand($this->invitee->wordPressUserId(), $invitation->id()->toString()));
        } finally {
            ini_set('error_log', (string) $log);
        }

        $logged = (string) file_get_contents($file);
        @unlink($file);

        $this->assertSame(ParticipantStatus::ACTIVE, $result->status);
        $this->assertSame(ParticipantInvitationStatus::ACCEPTED, $this->f->invitations->findById($invitation->id())->status()->toString());
        $this->assertStringContainsString('participant_invitation_accepted', $logged);
        $this->assertStringNotContainsString('example.com', $logged, 'No address in the log');
        $this->assertStringNotContainsString('招待 太郎', $logged);
    }

    public function test_an_expired_invitation_is_410(): void
    {
        $invitation = $this->f->invitation(self::EMAIL, null, ParticipantType::CAST, new DateTimeImmutable('-1 minute'));

        $this->expectException(ParticipantInvitationExpiredException::class);
        $this->accept($invitation);
    }

    public function test_a_manager_cancelled_invitation_is_not_pending(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $invitation->close(ParticipantInvitationCloseReason::manager(), $this->f->primaryManager->id());

        $this->expectException(ParticipantInvitationNotPendingException::class);
        $this->accept($invitation);
    }

    public function test_an_invitation_for_someone_else_is_404_like_a_missing_one(): void
    {
        $other = $this->f->person('other@example.com');
        $invitation = $this->f->invitation(self::EMAIL);

        foreach ([$invitation->id()->toString(), '99999999-9999-4999-8999-999999999999', 'not-a-uuid'] as $id) {
            try {
                $this->f->accept()->execute(new AcceptParticipantInvitationCommand($other->wordPressUserId(), $id));
                $this->fail('404 expected.');
            } catch (ParticipantInvitationNotFoundException $e) {
                $this->assertSame('Participant invitation not found.', $e->getMessage(), 'The same answer, nothing revealed');
            }
        }

        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
    }

    public function test_an_unverified_or_suspended_account_is_403(): void
    {
        $unverified = $this->f->person('late@example.com', false);
        $invitation = $this->f->invitation('late@example.com');

        try {
            $this->accept($invitation, $unverified);
            $this->fail('403 expected.');
        } catch (InvitationRecipientNotEligibleException $e) {
            $this->addToAssertionCount(1);
        }

        $suspended = $this->f->person('susp@example.com', true, 'SUSPENDED');

        $this->expectException(InvitationRecipientNotEligibleException::class);
        $this->accept($this->f->invitation('susp@example.com'), $suspended);
    }
}
