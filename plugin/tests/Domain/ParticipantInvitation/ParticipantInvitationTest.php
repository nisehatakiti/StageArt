<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class ParticipantInvitationTest extends TestCase
{
    private function givenPendingInvitation(?DateTimeImmutable $expiresAt = null): ParticipantInvitation
    {
        return ParticipantInvitation::create(
            ProductionId::generate(),
            'invitee@example.com',
            PersonId::generate(),
            ParticipantType::cast(),
            'some remarks',
            hash('sha256', 'raw-token-value'),
            $expiresAt ?? (new DateTimeImmutable())->add(new DateInterval('PT24H'))
        );
    }

    public function test_create_starts_pending_with_no_consumed_at(): void
    {
        $invitation = $this->givenPendingInvitation();

        $this->assertSame(ParticipantInvitationStatus::PENDING, $invitation->status()->toString());
        $this->assertNull($invitation->consumedAt());
        $this->assertTrue($invitation->isPending());
        $this->assertTrue($invitation->isUsable());
    }

    public function test_create_rejects_an_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ParticipantInvitation::create(
            ProductionId::generate(),
            'not-an-email',
            PersonId::generate(),
            ParticipantType::cast(),
            null,
            hash('sha256', 'x'),
            (new DateTimeImmutable())->add(new DateInterval('PT24H'))
        );
    }

    public function test_an_expired_invitation_is_not_usable_even_while_still_pending(): void
    {
        $invitation = $this->givenPendingInvitation((new DateTimeImmutable())->sub(new DateInterval('PT1H')));

        $this->assertTrue($invitation->isPending());
        $this->assertTrue($invitation->isExpired());
        $this->assertFalse($invitation->isUsable());
    }

    public function test_consume_transitions_to_consumed_and_sets_consumed_at(): void
    {
        $invitation = $this->givenPendingInvitation();

        $invitation->consume();

        $this->assertSame(ParticipantInvitationStatus::CONSUMED, $invitation->status()->toString());
        $this->assertNotNull($invitation->consumedAt());
        $this->assertFalse($invitation->isUsable());
    }

    public function test_consuming_a_non_pending_invitation_is_rejected(): void
    {
        $invitation = $this->givenPendingInvitation();
        $invitation->consume();

        $this->expectException(InvalidArgumentException::class);
        $invitation->consume();
    }

    public function test_cancel_transitions_to_cancelled(): void
    {
        $invitation = $this->givenPendingInvitation();

        $invitation->cancel();

        $this->assertSame(ParticipantInvitationStatus::CANCELLED, $invitation->status()->toString());
        $this->assertFalse($invitation->isUsable());
    }

    public function test_cancelling_a_non_pending_invitation_is_rejected(): void
    {
        $invitation = $this->givenPendingInvitation();
        $invitation->cancel();

        $this->expectException(InvalidArgumentException::class);
        $invitation->cancel();
    }

    public function test_rotate_token_replaces_the_hash_and_expiry_in_place(): void
    {
        $invitation = $this->givenPendingInvitation();
        $originalHash = $invitation->tokenHash();
        $newHash = hash('sha256', 'a-different-raw-token');
        $newExpiresAt = (new DateTimeImmutable())->add(new DateInterval('PT48H'));

        $invitation->rotateToken($newHash, $newExpiresAt);

        $this->assertNotSame($originalHash, $invitation->tokenHash());
        $this->assertSame($newHash, $invitation->tokenHash());
        $this->assertEquals($newExpiresAt, $invitation->expiresAt());
        $this->assertSame(ParticipantInvitationStatus::PENDING, $invitation->status()->toString());
    }

    /** §5/§20: an expired-but-still-PENDING invitation can be revived by
     * resend - rotateToken() only checks status, not expiry. */
    public function test_rotate_token_works_on_an_expired_but_still_pending_invitation(): void
    {
        $invitation = $this->givenPendingInvitation((new DateTimeImmutable())->sub(new DateInterval('PT1H')));
        $this->assertFalse($invitation->isUsable());

        $invitation->rotateToken(hash('sha256', 'fresh-token'), (new DateTimeImmutable())->add(new DateInterval('PT24H')));

        $this->assertTrue($invitation->isUsable());
    }

    public function test_rotate_token_on_a_cancelled_invitation_is_rejected(): void
    {
        $invitation = $this->givenPendingInvitation();
        $invitation->cancel();

        $this->expectException(InvalidArgumentException::class);
        $invitation->rotateToken(hash('sha256', 'x'), (new DateTimeImmutable())->add(new DateInterval('PT24H')));
    }

    public function test_reconstitute_preserves_every_field(): void
    {
        $id = ParticipantInvitationId::generate();
        $productionId = ProductionId::generate();
        $invitedBy = PersonId::generate();
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $expiresAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
        $consumedAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $invitation = ParticipantInvitation::reconstitute(
            $id,
            $productionId,
            'someone@example.com',
            $invitedBy,
            ParticipantType::staff(),
            'remarks here',
            'a-token-hash',
            ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::CONSUMED),
            $createdAt,
            $expiresAt,
            $consumedAt
        );

        $this->assertTrue($invitation->id()->equals($id));
        $this->assertTrue($invitation->productionId()->equals($productionId));
        $this->assertSame('someone@example.com', $invitation->email());
        $this->assertTrue($invitation->invitedByPersonId()->equals($invitedBy));
        $this->assertSame('STAFF', $invitation->participantType()->toString());
        $this->assertSame('remarks here', $invitation->remarks());
        $this->assertSame('a-token-hash', $invitation->tokenHash());
        $this->assertSame('CONSUMED', $invitation->status()->toString());
        $this->assertEquals($createdAt, $invitation->createdAt());
        $this->assertEquals($expiresAt, $invitation->expiresAt());
        $this->assertEquals($consumedAt, $invitation->consumedAt());
    }
}
