<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\ParticipantInvitation\CancelParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationAccessDeniedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotPendingException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationResult;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B stage 4 review fix (design doc §5.1 / §5.3 / §5.4 / §7.1):
 * `POST /participant-invitations/{id}/cancel` records CANCELLED(MANAGER)
 * with closed_at / closed_by_person_id / updated_at, is idempotent on
 * CANCELLED(MANAGER), and refuses every other terminal state with 409.
 */
final class CancelParticipantInvitationTest extends TestCase
{
    private ParticipantConsentFixture $f;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
    }

    private function cancel(ParticipantInvitation $invitation, ?Person $as = null): ParticipantInvitationResult
    {
        return $this->f->cancelInvitation()->execute(new CancelParticipantInvitationCommand(
            $invitation->id()->toString(),
            ($as ?? $this->f->primaryManager)->wordPressUserId()
        ));
    }

    private function stored(ParticipantInvitation $invitation): ParticipantInvitation
    {
        return $this->f->invitations->findById($invitation->id());
    }

    public function test_a_pending_invitation_is_cancelled_by_the_manager_with_reason_time_and_actor(): void
    {
        $manager = $this->f->participantManager();
        $invitation = $this->f->invitation('taro@example.com');
        $before = $invitation->updatedAt();
        $this->f->watchProductionLock();

        $result = $this->cancel($invitation, $manager);

        $stored = $this->stored($invitation);
        $this->assertSame('CANCELLED', $result->status);
        $this->assertSame('CANCELLED', $stored->status()->toString());
        $this->assertSame(ParticipantInvitationCloseReason::MANAGER, $stored->closeReason()->toString());
        $this->assertNotNull($stored->closedAt());
        $this->assertTrue($stored->closedByPersonId()->equals($manager->id()), 'The manager who cancelled');
        $this->assertEquals($stored->closedAt(), $stored->updatedAt());
        $this->assertGreaterThanOrEqual($before, $stored->updatedAt());
        $this->assertSame(['production', 'invitation'], $this->f->lockLog->order(), '§5.4');
        $this->assertSame([ParticipantEvent::INVITATION_CANCELLED], $this->f->eventStore->types());
        $detail = $this->f->eventStore->all()[0]->detail();
        $this->assertSame(['previous_status' => 'PENDING', 'close_reason' => 'MANAGER'], $detail);
        $this->assertTrue($this->f->eventStore->all()[0]->actorPersonId()->equals($manager->id()));
        $this->assertSame([], $this->f->notificationTypes(), 'No notification for a cancellation');
        $this->assertSame([], $this->f->mailer->invitationEmails);
    }

    public function test_an_expired_pending_invitation_can_be_cancelled(): void
    {
        $invitation = $this->f->invitation('taro@example.com', null, 'CAST', new DateTimeImmutable('-1 minute'));

        $this->assertSame('CANCELLED', $this->cancel($invitation)->status);
    }

    public function test_cancelling_again_returns_the_first_result_without_changes(): void
    {
        $invitation = $this->f->invitation('taro@example.com');
        $this->cancel($invitation);
        $first = clone $this->stored($invitation);
        $saves = $this->f->invitations->saves;

        $second = $this->cancel($invitation);

        $this->assertSame('CANCELLED', $second->status);
        $this->assertEquals($first, $this->stored($invitation), 'closed_at / closed_by / updated_at unchanged');
        $this->assertSame($saves, $this->f->invitations->saves, 'Not saved again');
        $this->assertCount(1, $this->f->eventStore->ofType(ParticipantEvent::INVITATION_CANCELLED), 'Not audited twice');
    }

    /**
     * @return array<string, array{0: callable}>
     */
    public static function terminalStates(): array
    {
        return [
            'CANCELLED(PRODUCTION_CLOSED)' => [static fn (ParticipantInvitation $i, Person $p) => $i->close(ParticipantInvitationCloseReason::productionClosed())],
            'CANCELLED(INVITER_REVOKED)' => [static fn (ParticipantInvitation $i, Person $p) => $i->close(ParticipantInvitationCloseReason::inviterRevoked())],
            'CANCELLED (pre-R14-B, no reason)' => [static fn (ParticipantInvitation $i, Person $p) => $i->cancel()],
            'COMPLETED (registered from the invitation)' => [static fn (ParticipantInvitation $i, Person $p) => $i->completeByEnrollment($p->id(), ParticipantId::generate())],
            'CONSUMED' => [static fn (ParticipantInvitation $i, Person $p) => $i->consume()],
        ];
    }

    /**
     * @dataProvider terminalStates
     */
    public function test_other_terminal_states_are_409_and_unchanged(callable $toState): void
    {
        $invitation = $this->f->invitation('taro@example.com');
        $toState($invitation, $this->f->person('taro@example.com'));
        $this->f->invitations->save($invitation);
        $before = clone $invitation;
        $saves = $this->f->invitations->saves;

        try {
            $this->cancel($invitation);
            $this->fail('409 not_pending expected.');
        } catch (ParticipantInvitationNotPendingException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertEquals($before, $this->stored($invitation));
        $this->assertSame($saves, $this->f->invitations->saves);
        $this->assertSame([], $this->f->eventStore->types());
    }

    public function test_only_a_manager_can_cancel(): void
    {
        $invitation = $this->f->invitation('taro@example.com');

        $this->expectException(ParticipantInvitationAccessDeniedException::class);

        try {
            $this->cancel($invitation, $this->f->person('someone@example.com'));
        } finally {
            $this->assertTrue($this->stored($invitation)->isPending());
        }
    }

    public function test_a_failed_save_rolls_everything_back(): void
    {
        $invitation = $this->f->invitation('taro@example.com');
        $before = clone $invitation;
        $this->f->invitations->failNextSave = new PersistenceException('update', 'wp_stageart_participant_invitations', 1146);

        try {
            $this->cancel($invitation);
            $this->fail('The failure must surface.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertEquals($before, $this->stored($invitation));
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame(1, $this->f->transactions->rollbacks());
    }

    public function test_a_failed_audit_write_rolls_the_cancellation_back(): void
    {
        $invitation = $this->f->invitation('taro@example.com');
        $before = clone $invitation;
        $this->f->eventStore->failNextAppend = new PersistenceException('insert into', 'wp_stageart_participant_events', 1146);

        try {
            $this->cancel($invitation);
            $this->fail('The failure must surface.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertEquals($before, $this->stored($invitation), 'Still PENDING, no close reason');
        $this->assertTrue($this->stored($invitation)->isPending());
        $this->assertSame(1, $this->f->transactions->rollbacks());
    }

    /** §5.3: cancellation first -> verifying the address registers nobody; the invitation stays CANCELLED(MANAGER). */
    public function test_a_cancelled_invitation_registers_nobody_on_verification(): void
    {
        $invitee = $this->f->person('taro@example.com');
        $cancelled = $this->f->invitation('taro@example.com');
        $this->cancel($cancelled);

        $this->f->resolve()->execute($invitee->id(), 'taro@example.com');

        $this->assertSame(ParticipantInvitationCloseReason::MANAGER, $this->stored($cancelled)->closeReason()->toString());
        $this->assertSame([], $this->f->participants->findByProductionId($this->f->production->id()));
    }

}
