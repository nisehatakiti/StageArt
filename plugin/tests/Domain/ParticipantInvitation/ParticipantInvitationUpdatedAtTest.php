<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\ParticipantInvitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\InvitationEmailAddress;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B stage 3: the invitation's updatedAt - set on creation and by every
 * actual change, untouched by an idempotent repeat, NULL kept for an
 * unchanged pre-R14-B row.
 */
final class ParticipantInvitationUpdatedAtTest extends TestCase
{
    private function legacy(string $status = ParticipantInvitationStatus::PENDING, ?ParticipantInvitationCloseReason $reason = null): ParticipantInvitation
    {
        return ParticipantInvitation::reconstitute(
            ParticipantInvitationId::generate(),
            ProductionId::generate(),
            'taro@example.com',
            'Taro',
            PersonId::generate(),
            ParticipantType::cast(),
            null,
            str_repeat('a', 64),
            ParticipantInvitationStatus::fromString($status),
            new DateTimeImmutable('2026-11-01T00:00:00+00:00'),
            new DateTimeImmutable('2030-01-01T00:00:00+00:00'),
            null,
            null,
            null,
            null,
            $reason,
            $reason !== null ? new DateTimeImmutable('2026-11-02T00:00:00+00:00') : null
        );
    }

    public function test_a_new_invitation_has_its_creation_time(): void
    {
        $invitation = ParticipantInvitation::create(
            ProductionId::generate(),
            'taro@example.com',
            'Taro',
            PersonId::generate(),
            ParticipantType::cast(),
            null,
            str_repeat('a', 64),
            new DateTimeImmutable('+7 days')
        );

        $this->assertEquals($invitation->createdAt(), $invitation->updatedAt());
    }

    public function test_a_legacy_row_keeps_null_until_it_changes(): void
    {
        $invitation = $this->legacy();

        $this->assertNull($invitation->updatedAt());
        $invitation->isUsable();
        $invitation->isExpiredAt(new DateTimeImmutable());
        $this->assertNull($invitation->updatedAt());
    }

    public function test_accept_sets_it_and_a_repeat_does_not(): void
    {
        $invitation = $this->legacy();
        $person = PersonId::generate();
        $at = new DateTimeImmutable('2026-11-17T00:00:00+00:00');

        $this->assertTrue($invitation->accept($person, ParticipantId::generate(), $at));
        $this->assertEquals($at, $invitation->updatedAt());

        $this->assertFalse($invitation->accept($person, $invitation->participantId(), new DateTimeImmutable('2026-11-18T00:00:00+00:00')));
        $this->assertEquals($at, $invitation->updatedAt());
    }

    public function test_reject_sets_it_and_a_repeat_does_not(): void
    {
        $invitation = $this->legacy(ParticipantInvitationStatus::CANCELLED, ParticipantInvitationCloseReason::inviterRevoked());
        $person = PersonId::generate();
        $at = new DateTimeImmutable('2026-11-17T00:00:00+00:00');

        $this->assertTrue($invitation->reject($person, null, $at));
        $this->assertEquals($at, $invitation->updatedAt());
        $this->assertEquals(new DateTimeImmutable('2026-11-02T00:00:00+00:00'), $invitation->closedAt(), 'closed_at is kept');

        $this->assertFalse($invitation->reject($person, null, new DateTimeImmutable('2026-11-18T00:00:00+00:00')));
        $this->assertEquals($at, $invitation->updatedAt());
    }

    public function test_close_and_resend_set_it_and_an_idempotent_manager_close_does_not(): void
    {
        $at = new DateTimeImmutable('2026-11-17T00:00:00+00:00');
        $resent = $this->legacy();
        $resent->resend($at);
        $this->assertEquals($at, $resent->updatedAt());

        $closed = $this->legacy();
        $manager = PersonId::generate();
        $this->assertTrue($closed->close(ParticipantInvitationCloseReason::manager(), $manager, $at));
        $this->assertEquals($at, $closed->updatedAt());
        $this->assertFalse($closed->close(ParticipantInvitationCloseReason::manager(), $manager, new DateTimeImmutable('2026-11-18T00:00:00+00:00')));
        $this->assertEquals($at, $closed->updatedAt());
    }

    /** §6.4: one comparison form for addresses. */
    public function test_addresses_are_compared_trimmed_and_lower_cased_only(): void
    {
        $this->assertSame('taro@example.com', InvitationEmailAddress::normalize("  Taro@Example.COM \n"));
        $this->assertTrue(InvitationEmailAddress::same('TARO@example.com', ' taro@example.com'));
        $this->assertFalse(InvitationEmailAddress::same('tarö@example.com', 'taro@example.com'));
        $this->assertFalse(InvitationEmailAddress::same('', ''));
    }
}
