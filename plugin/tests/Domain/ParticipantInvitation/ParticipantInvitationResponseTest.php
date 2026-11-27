<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\ParticipantInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B design doc §5.1 (status table / judgment table), §4.3, §7.2:
 * the invitee's accept / reject and the invalidation (close) record.
 */
final class ParticipantInvitationResponseTest extends TestCase
{
    private const NOW = '2026-11-20T12:00:00+09:00';

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    private function invitation(
        string $status = ParticipantInvitationStatus::PENDING,
        string $expiresAt = '2026-11-25T12:00:00+09:00',
        ?ParticipantInvitationCloseReason $closeReason = null,
        ?string $closedAt = null,
        ?PersonId $closedBy = null
    ): ParticipantInvitation {
        return ParticipantInvitation::reconstitute(
            ParticipantInvitationId::generate(),
            ProductionId::generate(),
            'invitee@example.com',
            '山田 太郎',
            PersonId::generate(),
            ParticipantType::cast(),
            null,
            'unused-token-hash',
            ParticipantInvitationStatus::fromString($status),
            new DateTimeImmutable('2026-11-18T12:00:00+09:00'),
            new DateTimeImmutable($expiresAt),
            null,
            null,
            null,
            null,
            $closeReason,
            $closedAt !== null ? new DateTimeImmutable($closedAt) : null,
            $closedBy
        );
    }

    private function expired(string $status = ParticipantInvitationStatus::PENDING, ?ParticipantInvitationCloseReason $reason = null): ParticipantInvitation
    {
        return $this->invitation($status, '2026-11-20T11:59:59+09:00', $reason, $reason !== null ? '2026-11-19T00:00:00+09:00' : null);
    }

    public function test_new_statuses_are_valid_and_derived_states_are_not_stored_statuses(): void
    {
        $this->assertSame('ACCEPTED', ParticipantInvitationStatus::fromString('ACCEPTED')->toString());
        $this->assertSame('REJECTED', ParticipantInvitationStatus::fromString('REJECTED')->toString());

        foreach (['EXPIRED', 'PRODUCTION_CLOSED', 'INVITER_REVOKED'] as $derived) {
            try {
                ParticipantInvitationStatus::fromString($derived);
                $this->fail("{$derived} must not be a stored status");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($derived, $exception->getMessage());
            }
        }
    }

    public function test_close_by_manager_records_reason_time_and_actor(): void
    {
        $invitation = $this->invitation();
        $manager = PersonId::generate();

        $this->assertTrue($invitation->close(ParticipantInvitationCloseReason::manager(), $manager, $this->now()));

        $this->assertSame(ParticipantInvitationStatus::CANCELLED, $invitation->status()->toString());
        $this->assertSame(ParticipantInvitationCloseReason::MANAGER, $invitation->closeReason()->toString());
        $this->assertEquals($this->now(), $invitation->closedAt());
        $this->assertTrue($invitation->closedByPersonId()->equals($manager));
    }

    public function test_a_manager_may_cancel_an_expired_pending_invitation(): void
    {
        $invitation = $this->expired();

        $this->assertTrue($invitation->close(ParticipantInvitationCloseReason::manager(), PersonId::generate(), $this->now()));
        $this->assertSame(ParticipantInvitationStatus::CANCELLED, $invitation->status()->toString());
    }

    public function test_cancelling_again_by_manager_is_an_idempotent_no_op_that_keeps_the_first_record(): void
    {
        $invitation = $this->invitation();
        $first = PersonId::generate();
        $invitation->close(ParticipantInvitationCloseReason::manager(), $first, $this->now());

        $this->assertFalse($invitation->close(ParticipantInvitationCloseReason::manager(), PersonId::generate(), new DateTimeImmutable('2026-11-21T00:00:00+09:00')));

        $this->assertTrue($invitation->closedByPersonId()->equals($first));
        $this->assertEquals($this->now(), $invitation->closedAt());
    }

    public function test_close_by_system_records_reason_and_time_without_an_actor(): void
    {
        $invitation = $this->invitation();

        $invitation->close(ParticipantInvitationCloseReason::inviterRevoked(), null, $this->now());

        $this->assertSame(ParticipantInvitationStatus::CANCELLED, $invitation->status()->toString());
        $this->assertSame(ParticipantInvitationCloseReason::INVITER_REVOKED, $invitation->closeReason()->toString());
        $this->assertEquals($this->now(), $invitation->closedAt());
        $this->assertNull($invitation->closedByPersonId());
    }

    public function test_close_validates_the_actor_against_the_reason(): void
    {
        foreach ([
            [ParticipantInvitationCloseReason::manager(), null],
            [ParticipantInvitationCloseReason::productionClosed(), PersonId::generate()],
        ] as [$reason, $actor]) {
            $invitation = $this->invitation();

            try {
                $invitation->close($reason, $actor, $this->now());
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(ParticipantInvitationStatus::PENDING, $invitation->status()->toString());
                $this->assertNull($invitation->closeReason());
            }
        }
    }

    /** §5.1: a recorded close reason (and its time) is never overwritten. */
    public function test_a_recorded_close_reason_is_never_overwritten(): void
    {
        $invitation = $this->invitation();
        $invitation->close(ParticipantInvitationCloseReason::productionClosed(), null, $this->now());

        foreach ([
            fn () => $invitation->close(ParticipantInvitationCloseReason::inviterRevoked(), null, new DateTimeImmutable('2026-11-21T00:00:00+09:00')),
            fn () => $invitation->close(ParticipantInvitationCloseReason::productionClosed(), null, new DateTimeImmutable('2026-11-21T00:00:00+09:00')),
            fn () => $invitation->close(ParticipantInvitationCloseReason::manager(), PersonId::generate(), new DateTimeImmutable('2026-11-21T00:00:00+09:00')),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(ParticipantInvitationCloseReason::PRODUCTION_CLOSED, $invitation->closeReason()->toString());
                $this->assertEquals($this->now(), $invitation->closedAt());
                $this->assertNull($invitation->closedByPersonId());
            }
        }
    }

    /** Past ACCEPTED / REJECTED rows (the removed R14-B answers) and completed (CONSUMED) ones are terminal: they cannot be closed. */
    public function test_accepted_and_rejected_invitations_cannot_be_closed(): void
    {
        foreach ([ParticipantInvitationStatus::ACCEPTED, ParticipantInvitationStatus::REJECTED, ParticipantInvitationStatus::CONSUMED] as $status) {
            $invitation = $this->invitation($status);

            try {
                $invitation->close(ParticipantInvitationCloseReason::manager(), PersonId::generate(), $this->now());
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->assertNull($invitation->closeReason());
            }
        }
    }

    /** CONSUMED (legacy, and now a completed invitation) is a valid terminal state: none of the operations apply. */
    public function test_legacy_consumed_invitation_is_compatible_and_terminal(): void
    {
        $invitation = $this->invitation(ParticipantInvitationStatus::CONSUMED);

        $this->assertSame(ParticipantInvitationStatus::CONSUMED, $invitation->status()->toString());
        $this->assertFalse($invitation->isUsable());

        foreach ([
            fn () => $invitation->completeByEnrollment(PersonId::generate(), ParticipantId::generate(), $this->now()),
            fn () => $invitation->close(ParticipantInvitationCloseReason::manager(), PersonId::generate(), $this->now()),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(ParticipantInvitationStatus::CONSUMED, $invitation->status()->toString());
            }
        }
    }

    public function test_reconstitute_preserves_the_r14b_fields(): void
    {
        $invitee = PersonId::generate();
        $participantId = ParticipantId::generate();
        $closedBy = PersonId::generate();

        $invitation = ParticipantInvitation::reconstitute(
            ParticipantInvitationId::generate(),
            ProductionId::generate(),
            'invitee@example.com',
            '山田 太郎',
            PersonId::generate(),
            ParticipantType::cast(),
            null,
            'hash',
            ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::REJECTED),
            new DateTimeImmutable('2026-11-18T12:00:00+09:00'),
            new DateTimeImmutable('2026-11-25T12:00:00+09:00'),
            null,
            $invitee,
            new DateTimeImmutable('2026-11-20T12:00:00+09:00'),
            $participantId,
            ParticipantInvitationCloseReason::manager(),
            new DateTimeImmutable('2026-11-19T12:00:00+09:00'),
            $closedBy
        );

        $this->assertTrue($invitation->inviteePersonId()->equals($invitee));
        $this->assertEquals(new DateTimeImmutable('2026-11-20T12:00:00+09:00'), $invitation->respondedAt());
        $this->assertTrue($invitation->participantId()->equals($participantId));
        $this->assertSame(ParticipantInvitationCloseReason::MANAGER, $invitation->closeReason()->toString());
        $this->assertEquals(new DateTimeImmutable('2026-11-19T12:00:00+09:00'), $invitation->closedAt());
        $this->assertTrue($invitation->closedByPersonId()->equals($closedBy));
    }

    public function test_reconstitute_rejects_a_close_reason_without_a_close_time(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->invitation(ParticipantInvitationStatus::CANCELLED, '2026-11-25T12:00:00+09:00', ParticipantInvitationCloseReason::manager(), null);
    }

    public function test_close_reason_rejects_unknown_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ParticipantInvitationCloseReason::fromString('EXPIRED');
    }

    /** Invitation policy 2026-11-27: a PENDING invitation is completed by registering the invitee - CONSUMED, no response recorded. */
    public function test_a_pending_invitation_is_completed_by_the_enrollment(): void
    {
        $invitation = $this->invitation();
        $invitee = PersonId::generate();
        $participant = ParticipantId::generate();

        $invitation->completeByEnrollment($invitee, $participant, $this->now());

        $this->assertSame(ParticipantInvitationStatus::CONSUMED, $invitation->status()->toString());
        $this->assertEquals($this->now(), $invitation->consumedAt());
        $this->assertEquals($this->now(), $invitation->updatedAt());
        $this->assertTrue($invitation->inviteePersonId()->equals($invitee));
        $this->assertTrue($invitation->participantId()->equals($participant));
        $this->assertNull($invitation->respondedAt(), 'Nobody answered the invitation');
        $this->assertFalse($invitation->isUsable());
    }

    public function test_an_expired_cancelled_or_answered_invitation_cannot_be_completed(): void
    {
        foreach ([
            $this->expired(),
            $this->invitation(ParticipantInvitationStatus::CANCELLED, '2026-11-25T12:00:00+09:00', ParticipantInvitationCloseReason::manager(), '2026-11-19T00:00:00+09:00', PersonId::generate()),
            $this->invitation(ParticipantInvitationStatus::ACCEPTED),
            $this->invitation(ParticipantInvitationStatus::REJECTED),
        ] as $invitation) {
            $before = $invitation->status()->toString();

            try {
                $invitation->completeByEnrollment(PersonId::generate(), ParticipantId::generate(), $this->now());
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame($before, $invitation->status()->toString());
                $this->assertNull($invitation->participantId());
            }
        }
    }

}
