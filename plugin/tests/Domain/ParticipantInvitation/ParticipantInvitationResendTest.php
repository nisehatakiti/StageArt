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
 * R14-B design doc D4 (7-day lifetime), D6 / §5.3 (5 resends per
 * invitation), U5 C案 (a resend does not change the token).
 */
final class ParticipantInvitationResendTest extends TestCase
{
    private function invitation(
        string $status = ParticipantInvitationStatus::PENDING,
        string $expiresAt = '2026-11-25T12:00:00+09:00',
        int $resendCount = 0
    ): ParticipantInvitation {
        return ParticipantInvitation::reconstitute(
            ParticipantInvitationId::generate(),
            ProductionId::generate(),
            'invitee@example.com',
            '山田 太郎',
            PersonId::generate(),
            ParticipantType::cast(),
            null,
            'the-stored-token-hash',
            ParticipantInvitationStatus::fromString($status),
            new DateTimeImmutable('2026-11-18T12:00:00+09:00'),
            new DateTimeImmutable($expiresAt),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $resendCount
        );
    }

    public function test_the_invitation_lifetime_is_seven_days(): void
    {
        $this->assertSame('P7D', ParticipantInvitation::TOKEN_LIFETIME_SPEC);
        $this->assertSame(5, ParticipantInvitation::MAX_RESENDS);
        $this->assertEquals(
            new DateTimeImmutable('2026-11-27T09:30:00+09:00'),
            ParticipantInvitation::expiresAtFrom(new DateTimeImmutable('2026-11-20T09:30:00+09:00'))
        );
    }

    public function test_a_new_invitation_starts_with_no_resends(): void
    {
        $invitation = ParticipantInvitation::create(
            ProductionId::generate(),
            'invitee@example.com',
            '山田 太郎',
            PersonId::generate(),
            ParticipantType::cast(),
            null,
            'hash',
            ParticipantInvitation::expiresAtFrom(new DateTimeImmutable())
        );

        $this->assertSame(0, $invitation->resendCount());
        $this->assertSame(5, $invitation->remainingResends());
        $this->assertFalse($invitation->hasReachedResendLimit());
    }

    public function test_resend_moves_the_expiry_to_seven_days_from_the_resend_and_counts_it(): void
    {
        $invitation = $this->invitation();
        $now = new DateTimeImmutable('2026-11-24T08:00:00+09:00');

        $invitation->resend($now);

        $this->assertEquals(new DateTimeImmutable('2026-12-01T08:00:00+09:00'), $invitation->expiresAt());
        $this->assertSame(1, $invitation->resendCount());
        $this->assertSame(4, $invitation->remainingResends());
        $this->assertSame(ParticipantInvitationStatus::PENDING, $invitation->status()->toString());
    }

    /** U5 C案: the e-mail carries no token, so a resend never rotates it. */
    public function test_resend_does_not_change_the_token(): void
    {
        $invitation = $this->invitation();

        $invitation->resend(new DateTimeImmutable('2026-11-24T08:00:00+09:00'));

        $this->assertSame('the-stored-token-hash', $invitation->tokenHash());
    }

    public function test_resend_revives_an_expired_pending_invitation(): void
    {
        $invitation = $this->invitation(ParticipantInvitationStatus::PENDING, '2026-11-19T12:00:00+09:00');
        $now = new DateTimeImmutable('2026-11-20T12:00:00+09:00');
        $this->assertTrue($invitation->isExpiredAt($now));

        $invitation->resend($now);

        $this->assertFalse($invitation->isExpiredAt($now));
        $this->assertTrue($invitation->accept(PersonId::generate(), ParticipantId::generate(), $now));
    }

    /** D6: 5 resends (6 sends with the first one); the 6th resend is refused and changes nothing. */
    public function test_the_sixth_resend_is_refused(): void
    {
        $invitation = $this->invitation();
        $now = new DateTimeImmutable('2026-11-20T12:00:00+09:00');

        for ($i = 1; $i <= 5; $i++) {
            $invitation->resend($now);
        }

        $this->assertSame(5, $invitation->resendCount());
        $this->assertSame(0, $invitation->remainingResends());
        $this->assertTrue($invitation->hasReachedResendLimit());
        $expiresAt = $invitation->expiresAt();

        try {
            $invitation->resend(new DateTimeImmutable('2026-11-21T12:00:00+09:00'));
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(5, $invitation->resendCount());
            $this->assertEquals($expiresAt, $invitation->expiresAt());
        }
    }

    public function test_a_reconstituted_count_at_the_limit_is_refused(): void
    {
        $invitation = $this->invitation(ParticipantInvitationStatus::PENDING, '2026-11-25T12:00:00+09:00', 5);

        $this->expectException(InvalidArgumentException::class);
        $invitation->resend(new DateTimeImmutable('2026-11-20T12:00:00+09:00'));
    }

    public function test_a_negative_resend_count_is_rejected_on_reconstitute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->invitation(ParticipantInvitationStatus::PENDING, '2026-11-25T12:00:00+09:00', -1);
    }

    /** Only PENDING can be resent: not CANCELLED (any reason), ACCEPTED, REJECTED or CONSUMED. */
    public function test_non_pending_invitations_cannot_be_resent(): void
    {
        $now = new DateTimeImmutable('2026-11-20T12:00:00+09:00');
        $accepted = $this->invitation();
        $accepted->accept(PersonId::generate(), ParticipantId::generate(), $now);
        $rejected = $this->invitation();
        $rejected->reject(PersonId::generate(), null, $now);
        $cancelled = $this->invitation();
        $cancelled->close(ParticipantInvitationCloseReason::manager(), PersonId::generate(), $now);
        $consumed = $this->invitation(ParticipantInvitationStatus::CONSUMED);

        foreach ([$accepted, $rejected, $cancelled, $consumed] as $invitation) {
            try {
                $invitation->resend($now);
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(0, $invitation->resendCount());
            }
        }
    }
}
