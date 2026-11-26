<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Participant\ParticipantConsentNotifier;
use StageArt\Application\ParticipantInvitation\AcceptParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationClosedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationExpiredException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotPendingException;
use StageArt\Application\ParticipantInvitation\RejectParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\RejectParticipantInvitationResult;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §4.3 / §5.1 / §7.2 reject (U7, U10).
 */
final class RejectParticipantInvitationUseCaseTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;
    private Person $invitee;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->invitee = $this->f->person(self::EMAIL);
    }

    private function reject(ParticipantInvitation $invitation): RejectParticipantInvitationResult
    {
        return $this->f->reject()->execute(new RejectParticipantInvitationCommand($this->invitee->wordPressUserId(), $invitation->id()->toString()));
    }

    /** An invitation the system closed earlier (as an acceptance attempt does). */
    private function closedBySystem(ParticipantInvitationCloseReason $reason, ?DateTimeImmutable $expiresAt = null): ParticipantInvitation
    {
        $invitation = $this->f->invitation(self::EMAIL, null, ParticipantType::CAST, $expiresAt);
        $invitation->close($reason, null, new DateTimeImmutable('-1 hour'));

        return $invitation;
    }

    public function test_a_pending_invitation_becomes_rejected_without_any_participant(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->watchProductionLock();

        $result = $this->reject($invitation);

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationStatus::REJECTED, $result->status);
        $this->assertSame(ParticipantInvitationStatus::REJECTED, $stored->status()->toString());
        $this->assertTrue($stored->inviteePersonId()->equals($this->invitee->id()));
        $this->assertNotNull($stored->respondedAt());
        $this->assertNull($stored->closeReason());
        $this->assertSame([], $this->f->participants->findByProductionId($this->f->production->id()), 'A rejection creates no Participant');
        $this->assertSame([ParticipantEvent::REJECTED], $this->f->eventStore->types());
        $this->assertSame(ParticipantInvitationStatus::PENDING, $this->f->eventStore->all()[0]->detail()['previous_status']);
        $this->assertSame([ParticipantConsentNotifier::TYPE_INVITATION_REJECTED], $this->f->notificationTypes());
        $this->assertTrue($this->f->dispatcher->dispatched()[0]['personId']->equals($this->f->primaryManager->id()));
        $this->assertStringContainsString('招待 太郎さん', $this->f->dispatcher->dispatched()[0]['payload']['message']);
        $this->assertSame(['production', 'invitation'], $this->f->lockLog->order());
        $this->assertSame(0, $this->f->invitations->tokenHashLookups);
    }

    public function test_an_existing_participant_row_is_not_changed(): void
    {
        $row = $this->f->participantRow($this->invitee, ParticipantStatus::ACTIVE);
        $updatedAt = $row->updatedAt();

        $this->reject($this->f->invitation(self::EMAIL));

        $this->assertSame(ParticipantStatus::ACTIVE, $this->f->participants->findById($row->id())->status()->toString());
        $this->assertEquals($updatedAt, $this->f->participants->findById($row->id())->updatedAt());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function systemReasons(): array
    {
        return [
            'PRODUCTION_CLOSED' => [ParticipantInvitationCloseReason::PRODUCTION_CLOSED],
            'INVITER_REVOKED' => [ParticipantInvitationCloseReason::INVITER_REVOKED],
        ];
    }

    /**
     * @dataProvider systemReasons
     */
    public function test_a_system_closed_invitation_can_be_rejected_keeping_its_close_reason(string $reason): void
    {
        $invitation = $this->closedBySystem(ParticipantInvitationCloseReason::fromString($reason));
        $closedAt = $invitation->closedAt();

        $result = $this->reject($invitation);

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationStatus::REJECTED, $stored->status()->toString());
        $this->assertSame($reason, $stored->closeReason()->toString());
        $this->assertEquals($closedAt, $stored->closedAt(), 'closed_at is never changed');
        $this->assertSame($reason, $result->closeReason);
        $this->assertSame([ParticipantEvent::REJECTED], $this->f->eventStore->types(), 'No second INVITATION_CLOSED');
        $this->assertSame(ParticipantInvitationStatus::CANCELLED, $this->f->eventStore->all()[0]->detail()['previous_status']);
    }

    /** U10: rejected after accept already notified the revocation - no second revocation notice. */
    public function test_after_an_acceptance_attempt_notified_the_revocation_the_rejection_does_not_repeat_it(): void
    {
        $manager = $this->f->participantManager();
        $invitation = $this->f->invitation(self::EMAIL, $manager);

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $row) {
            $row->deactivate($this->f->primaryManager->id());
        }

        try {
            $this->f->accept()->execute(new AcceptParticipantInvitationCommand($this->invitee->wordPressUserId(), $invitation->id()->toString()));
            $this->fail('409 closed expected.');
        } catch (ParticipantInvitationClosedException $e) {
            $this->addToAssertionCount(1);
        }

        $this->reject($invitation);

        $this->assertSame(
            [ParticipantConsentNotifier::TYPE_INVITATION_CLOSED, ParticipantConsentNotifier::TYPE_INVITATION_REJECTED],
            $this->f->notificationTypes()
        );
        $rejected = $this->f->dispatcher->dispatched()[1];
        $this->assertTrue($rejected['personId']->equals($this->f->primaryManager->id()), 'The inviter has no permission: the PrimaryManager is told');
        $this->assertStringNotContainsString('招待者の権限がなくなっていた', $rejected['payload']['message']);
    }

    /** U10: a rejection that first detects INVITER_REVOKED records it and sends one combined notice. */
    public function test_a_rejection_that_first_detects_a_revoked_inviter_records_and_notifies_once(): void
    {
        $manager = $this->f->participantManager();
        $invitation = $this->f->invitation(self::EMAIL, $manager);

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $row) {
            $row->deactivate($this->f->primaryManager->id());
        }

        $this->reject($invitation);
        $this->reject($invitation);

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationStatus::REJECTED, $stored->status()->toString());
        $this->assertSame(ParticipantInvitationCloseReason::INVITER_REVOKED, $stored->closeReason()->toString());
        $this->assertNotNull($stored->closedAt());
        $this->assertNotNull($this->f->invitations->revocationNotifiedAt($invitation->id()));
        $this->assertSame([ParticipantConsentNotifier::TYPE_INVITATION_REJECTED], $this->f->notificationTypes(), 'One notification for both');
        $this->assertTrue($this->f->dispatcher->dispatched()[0]['personId']->equals($this->f->primaryManager->id()));
        $this->assertStringContainsString('招待者の権限がなくなっていた', $this->f->dispatcher->dispatched()[0]['payload']['message']);
        $this->assertSame([ParticipantEvent::INVITATION_CLOSED, ParticipantEvent::REJECTED], $this->f->eventStore->types(), 'Once, though rejected twice');
        $closed = $this->f->eventStore->all()[0]->detail();
        $this->assertSame('REJECT', $closed['detected_by']);
        $this->assertTrue($closed['primary_manager_notify_attempted']);
    }

    public function test_a_rejection_that_first_detects_a_closed_production_records_it_without_a_revocation_notice(): void
    {
        $this->f->forceProductionStatus(ProductionStatus::CANCELLED);
        $invitation = $this->f->invitation(self::EMAIL);

        $this->reject($invitation);

        $stored = $this->f->invitations->findById($invitation->id());
        $this->assertSame(ParticipantInvitationCloseReason::PRODUCTION_CLOSED, $stored->closeReason()->toString());
        $this->assertNull($this->f->invitations->revocationNotifiedAt($invitation->id()));
        $this->assertSame([ParticipantEvent::INVITATION_CLOSED, ParticipantEvent::REJECTED], $this->f->eventStore->types());
        $this->assertFalse($this->f->eventStore->all()[0]->detail()['primary_manager_notify_attempted']);
        $this->assertSame([ParticipantConsentNotifier::TYPE_INVITATION_REJECTED], $this->f->notificationTypes());
    }

    public function test_a_second_rejection_returns_the_first_result_without_side_effects(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->reject($invitation);
        $respondedAt = $this->f->invitations->findById($invitation->id())->respondedAt();

        $second = $this->reject($invitation);

        $this->assertSame(ParticipantInvitationStatus::REJECTED, $second->status);
        $this->assertEquals($respondedAt, $this->f->invitations->findById($invitation->id())->respondedAt());
        $this->assertSame([ParticipantEvent::REJECTED], $this->f->eventStore->types());
        $this->assertCount(1, $this->f->notificationTypes());
    }

    public function test_a_manager_cancelled_invitation_cannot_be_rejected(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $invitation->close(ParticipantInvitationCloseReason::manager(), $this->f->primaryManager->id());

        $this->expectException(ParticipantInvitationNotPendingException::class);
        $this->reject($invitation);
    }

    public function test_a_legacy_cancelled_invitation_without_reason_cannot_be_rejected(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $invitation->cancel();

        $this->expectException(ParticipantInvitationNotPendingException::class);
        $this->reject($invitation);
    }

    public function test_an_accepted_invitation_cannot_be_rejected(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->accept()->execute(new AcceptParticipantInvitationCommand($this->invitee->wordPressUserId(), $invitation->id()->toString()));

        $this->expectException(ParticipantInvitationNotPendingException::class);
        $this->reject($invitation);
    }

    public function test_an_expired_invitation_is_410_even_after_a_system_close(): void
    {
        $pending = $this->f->invitation(self::EMAIL, null, ParticipantType::CAST, new DateTimeImmutable('-1 minute'));

        try {
            $this->reject($pending);
            $this->fail('410 expected.');
        } catch (ParticipantInvitationExpiredException $e) {
            $this->addToAssertionCount(1);
        }

        $closed = $this->closedBySystem(ParticipantInvitationCloseReason::productionClosed(), new DateTimeImmutable('-1 minute'));

        $this->expectException(ParticipantInvitationExpiredException::class);
        $this->reject($closed);
    }

    public function test_an_invitation_for_someone_else_is_404(): void
    {
        $other = $this->f->person('other@example.com');
        $invitation = $this->f->invitation(self::EMAIL);

        $this->expectException(ParticipantInvitationNotFoundException::class);
        $this->f->reject()->execute(new RejectParticipantInvitationCommand($other->wordPressUserId(), $invitation->id()->toString()));
    }

    public function test_nothing_personal_is_recorded_or_sent(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->reject($invitation);

        $recorded = $this->f->everythingRecordedAndSent();
        $this->assertStringNotContainsString(self::EMAIL, $recorded);
        $this->assertStringNotContainsString($invitation->tokenHash(), $recorded);
        $this->assertStringNotContainsString('internal memo', $recorded);
        $this->assertTrue(ParticipantInvitationId::fromString($invitation->id()->toString())->equals($invitation->id()));
    }
}
