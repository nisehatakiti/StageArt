<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Participant\ParticipantConsentNotifier;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27):
 * once an address is verified (or signed in with Google), every usable
 * Production invitation to it registers the Person - no acceptance, no
 * consent recorded. Ported from the R14-B acceptance tests: the same row
 * rules (U13), DRAFT (N11), dormant appointments (U15), closed production
 * (D5), revoked inviter (D3 / U10), lock order (§5.4) and rollback.
 */
final class ResolveParticipantInvitationUseCaseTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;
    private Person $invitee;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->invitee = $this->f->person(self::EMAIL);
    }

    private function resolve(?Person $as = null, string $email = self::EMAIL): void
    {
        $this->f->resolve()->execute(($as ?? $this->invitee)->id(), $email);
    }

    private function participantOf(Person $person, string $type = ParticipantType::CAST): ?Participant
    {
        return $this->f->participants->findByProductionAndSubject(
            $this->f->production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::fromString($type)
        );
    }

    /** @return string what error_log() wrote while $run ran */
    private function captureLog(callable $run): string
    {
        $log = ini_get('error_log');
        $file = tempnam(sys_get_temp_dir(), 'inv');
        ini_set('error_log', $file);

        try {
            $run();
        } finally {
            ini_set('error_log', (string) $log);
        }

        $logged = (string) file_get_contents($file);
        @unlink($file);

        return $logged;
    }

    public function test_a_new_participant_is_registered_active_without_any_consent(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);

        $this->resolve();

        $participant = $this->participantOf($this->invitee);
        $this->assertNotNull($participant);
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantEnrollmentSource::INVITATION, $participant->enrollmentSource()->toString());
        $this->assertNull($participant->consentSource(), 'No consent was given in StageArt - none is recorded');
        $this->assertNull($participant->consentedAt());
        $this->assertSame('招待 太郎', $participant->displayName(), 'display_name is the name the manager typed');

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationStatus::CONSUMED, $stored->status()->toString());
        $this->assertNotNull($stored->consumedAt());
        $this->assertTrue($stored->inviteePersonId()->equals($this->invitee->id()));
        $this->assertTrue($stored->participantId()->equals($participant->id()));
        $this->assertNull($stored->respondedAt(), 'Nobody answered the invitation');

        $this->assertSame([ParticipantEvent::INVITATION_ENROLLED], $this->f->eventStore->types());
        $event = $this->f->eventStore->ofType(ParticipantEvent::INVITATION_ENROLLED)[0];
        $this->assertNull($event->detail()['previous_status']);
        $this->assertSame('ADDRESS_VERIFIED', $event->detail()['trigger']);
        $this->assertSame([], $this->f->notificationTypes(), 'No acceptance notification any more');
        $this->assertSame(1, $this->f->transactions->commits());

        $recorded = $this->f->everythingRecordedAndSent();
        $this->assertStringNotContainsString(self::EMAIL, $recorded);
        $this->assertStringNotContainsString('internal memo', $recorded);
    }

    public function test_running_twice_registers_once(): void
    {
        $this->f->invitation(self::EMAIL);

        $this->resolve();
        $this->resolve();

        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertSame([ParticipantEvent::INVITATION_ENROLLED], $this->f->eventStore->types(), 'One audit event');
    }

    public function test_an_active_participant_only_completes_the_invitation(): void
    {
        $existing = $this->f->participantRow($this->invitee, ParticipantStatus::ACTIVE);
        $delegate = $this->f->delegate($this->invitee);
        $invitation = $this->f->invitation(self::EMAIL);

        $this->resolve();

        $participant = $this->participantOf($this->invitee);
        $this->assertTrue($participant->id()->equals($existing->id()));
        $this->assertNull($participant->consentedAt(), 'Nothing recorded on the existing row');
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive(), 'U15: not deactivated');
        $this->assertSame(ParticipantInvitationStatus::CONSUMED, $this->f->invitations->findById($invitation->id())->status()->toString());
        $this->assertSame([], $this->f->eventStore->types(), 'The row did not change');
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
        $this->f->invitation(self::EMAIL);
        $this->f->watchProductionLock();

        $this->resolve();

        $participant = $this->participantOf($this->invitee);
        $this->assertTrue($participant->id()->equals($existing->id()), 'No new Participant id for the same key');
        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString(), 'enrollmentSource is never changed');
        $this->assertFalse($this->f->delegates->findById($leftActive->id())->isActive(), 'DORMANT_ON_ENROLLMENT');
        $this->assertSame([ParticipantEvent::DELEGATE_DEACTIVATED, ParticipantEvent::INVITATION_ENROLLED], $this->f->eventStore->types());
        $detail = $this->f->eventStore->ofType(ParticipantEvent::INVITATION_ENROLLED)[0]->detail();
        $this->assertSame($status, $detail['previous_status']);
        $this->assertTrue($detail['reused_participant']);
        $this->assertSame(['production', 'invitation', 'participant', 'delegate'], $this->f->lockLog->order(), '§5.4 lock order');
    }

    public function test_a_pending_join_request_row_becomes_active(): void
    {
        $request = $this->f->participantRow($this->invitee, ParticipantStatus::PENDING, ParticipantType::CAST, ParticipantEnrollmentSource::JOIN_REQUEST, new DateTimeImmutable('-1 day'));
        $this->f->invitation(self::EMAIL);

        $this->resolve();

        $this->assertSame(ParticipantStatus::ACTIVE, $this->f->participants->findById($request->id())->status()->toString());
        $this->assertTrue($this->f->eventStore->ofType(ParticipantEvent::INVITATION_ENROLLED)[0]->detail()['superseded_join_request']);
    }

    public function test_a_draft_of_the_same_type_registers_nothing_and_the_invitation_stays_pending(): void
    {
        $draft = $this->f->participantRow($this->invitee, ParticipantStatus::DRAFT);
        $invitation = $this->f->invitation(self::EMAIL);

        $logged = $this->captureLog(fn () => $this->resolve());

        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
        $this->assertSame(ParticipantStatus::DRAFT, $this->f->participants->findById($draft->id())->status()->toString());
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame(1, $this->f->transactions->rollbacks());
        $this->assertStringContainsString('ParticipantDraftExistsException', $logged);
        $this->assertStringNotContainsString(self::EMAIL, $logged, 'No address in the log');
    }

    public function test_a_person_active_in_the_other_type_keeps_their_appointments(): void
    {
        $this->f->participantRow($this->invitee, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $delegate = $this->f->delegate($this->invitee);
        $this->f->invitation(self::EMAIL, null, ParticipantType::CAST);

        $this->resolve();

        $this->assertSame(ParticipantStatus::ACTIVE, $this->participantOf($this->invitee)->status()->toString());
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
    }

    public function test_a_closed_production_closes_the_invitation_and_registers_nobody(): void
    {
        $this->f->forceProductionStatus(ProductionStatus::COMPLETED);
        $invitation = $this->f->invitation(self::EMAIL);

        $this->resolve();

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationCloseReason::PRODUCTION_CLOSED, $stored->closeReason()->toString());
        $this->assertNull($this->participantOf($this->invitee));
        $this->assertSame([ParticipantEvent::INVITATION_CLOSED], $this->f->eventStore->types());
        $this->assertSame('ENROLL', $this->f->eventStore->all()[0]->detail()['detected_by']);
        $this->assertSame([], $this->f->notificationTypes());
    }

    public function test_an_inviter_without_permission_closes_the_invitation_and_notifies_once(): void
    {
        $manager = $this->f->participantManager();
        $invitation = $this->f->invitation(self::EMAIL, $manager);

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $row) {
            $row->deactivate($this->f->primaryManager->id());
        }

        $this->resolve();
        $this->resolve();

        $this->assertSame(ParticipantInvitationCloseReason::INVITER_REVOKED, $this->f->invitations->findById($invitation->id())->closeReason()->toString());
        $this->assertNull($this->participantOf($this->invitee));
        $this->assertSame([ParticipantConsentNotifier::TYPE_INVITATION_CLOSED], $this->f->notificationTypes(), 'Attempted once');
        $this->assertTrue($this->f->dispatcher->dispatched()[0]['personId']->equals($this->f->primaryManager->id()));
    }

    public function test_a_failed_participant_write_rolls_back_and_is_logged(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->participants->failNextSave = new PersistenceException('insert into', 'wp_stageart_participants', 1146);

        $logged = $this->captureLog(fn () => $this->resolve());

        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
        $this->assertNull($this->participantOf($this->invitee));
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame(1, $this->f->transactions->rollbacks());
        $this->assertStringContainsString($invitation->id()->toString(), $logged);
    }

    public function test_a_failed_audit_write_rolls_the_registration_back(): void
    {
        $existing = $this->f->participantRow($this->invitee, ParticipantStatus::WITHDRAWN);
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->eventStore->failNextAppend = new PersistenceException('insert into', 'wp_stageart_participant_events', 1146);

        $this->captureLog(fn () => $this->resolve());

        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
        $this->assertSame(ParticipantStatus::WITHDRAWN, $this->f->participants->findById($existing->id())->status()->toString());
    }

    public function test_an_expired_invitation_registers_nobody(): void
    {
        $invitation = $this->f->invitation(self::EMAIL, null, ParticipantType::CAST, new DateTimeImmutable('-1 minute'));

        $this->resolve();

        $this->assertNull($this->participantOf($this->invitee));
        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
    }

    public function test_a_cancelled_invitation_registers_nobody(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $invitation->close(ParticipantInvitationCloseReason::manager(), $this->f->primaryManager->id());
        $this->f->invitations->save($invitation);

        $this->resolve();

        $this->assertNull($this->participantOf($this->invitee));
        $this->assertSame([], $this->f->eventStore->types());
    }

    public function test_an_invitation_to_another_address_is_not_used(): void
    {
        $this->f->invitation('someone-else@example.com');

        $this->resolve();

        $this->assertNull($this->participantOf($this->invitee));
    }

    public function test_an_address_verified_by_another_person_registers_nobody(): void
    {
        $owner = $this->f->person('shared@example.com');
        $invitation = $this->f->invitation('shared@example.com');

        $this->resolve($this->invitee, 'shared@example.com');

        $this->assertNull($this->participantOf($this->invitee));
        $this->assertNull($this->participantOf($owner));
        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
    }

    public function test_the_address_is_matched_case_and_space_insensitively(): void
    {
        $this->f->invitation(' Taro@Example.com ');

        $this->resolve();

        $this->assertNotNull($this->participantOf($this->invitee));
    }

    public function test_an_invitation_of_another_production_with_no_production_row_is_skipped(): void
    {
        $invitation = ParticipantInvitation::create(
            \StageArt\Domain\Production\ProductionId::generate(),
            self::EMAIL,
            '招待 太郎',
            $this->f->primaryManager->id(),
            ParticipantType::cast(),
            null,
            hash('sha256', 'x'),
            new DateTimeImmutable('+7 days')
        );
        $this->f->invitations->save($invitation);

        $this->resolve();

        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->invitations->findById($invitation->id())->status()->toString());
    }
}
