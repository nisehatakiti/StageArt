<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\OrganizationInvitation;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationStatus;
use StageArt\Domain\Person\PersonId;

final class OrganizationInvitationTest extends TestCase
{
    private function givenPendingInvitation(?DateTimeImmutable $expiresAt = null): OrganizationInvitation
    {
        return OrganizationInvitation::create(
            OrganizationId::generate(),
            'invitee@example.com',
            '山田 太郎',
            PersonId::generate(),
            'some remarks',
            hash('sha256', 'raw-token-value'),
            $expiresAt ?? (new DateTimeImmutable())->add(new DateInterval('PT24H'))
        );
    }

    public function test_create_starts_pending_with_no_consumed_at(): void
    {
        $invitation = $this->givenPendingInvitation();

        $this->assertSame(OrganizationInvitationStatus::PENDING, $invitation->status()->toString());
        $this->assertNull($invitation->consumedAt());
        $this->assertTrue($invitation->isPending());
        $this->assertTrue($invitation->isUsable());
    }

    public function test_create_stores_the_invited_name(): void
    {
        $invitation = $this->givenPendingInvitation();

        $this->assertSame('山田 太郎', $invitation->invitedName());
    }

    public function test_create_trims_the_invited_name(): void
    {
        $invitation = OrganizationInvitation::create(
            OrganizationId::generate(),
            'invitee@example.com',
            '  山田 太郎  ',
            PersonId::generate(),
            null,
            hash('sha256', 'x'),
            (new DateTimeImmutable())->add(new DateInterval('PT24H'))
        );

        $this->assertSame('山田 太郎', $invitation->invitedName());
    }

    public function test_create_rejects_an_empty_invited_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OrganizationInvitation::create(
            OrganizationId::generate(),
            'invitee@example.com',
            '   ',
            PersonId::generate(),
            null,
            hash('sha256', 'x'),
            (new DateTimeImmutable())->add(new DateInterval('PT24H'))
        );
    }

    public function test_create_rejects_an_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OrganizationInvitation::create(
            OrganizationId::generate(),
            'not-an-email',
            '山田 太郎',
            PersonId::generate(),
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

        $this->assertSame(OrganizationInvitationStatus::CONSUMED, $invitation->status()->toString());
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

        $this->assertSame(OrganizationInvitationStatus::CANCELLED, $invitation->status()->toString());
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
        $this->assertSame(OrganizationInvitationStatus::PENDING, $invitation->status()->toString());
    }

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
        $id = OrganizationInvitationId::generate();
        $organizationId = OrganizationId::generate();
        $invitedBy = PersonId::generate();
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $expiresAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
        $consumedAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $invitation = OrganizationInvitation::reconstitute(
            $id,
            $organizationId,
            'someone@example.com',
            '鈴木 花子',
            $invitedBy,
            'remarks here',
            'a-token-hash',
            OrganizationInvitationStatus::fromString(OrganizationInvitationStatus::CONSUMED),
            $createdAt,
            $expiresAt,
            $consumedAt
        );

        $this->assertTrue($invitation->id()->equals($id));
        $this->assertTrue($invitation->organizationId()->equals($organizationId));
        $this->assertSame('someone@example.com', $invitation->email());
        $this->assertSame('鈴木 花子', $invitation->invitedName());
        $this->assertTrue($invitation->invitedByPersonId()->equals($invitedBy));
        $this->assertSame('remarks here', $invitation->remarks());
        $this->assertSame('a-token-hash', $invitation->tokenHash());
        $this->assertSame('CONSUMED', $invitation->status()->toString());
        $this->assertEquals($createdAt, $invitation->createdAt());
        $this->assertEquals($expiresAt, $invitation->expiresAt());
        $this->assertEquals($consumedAt, $invitation->consumedAt());
    }
}
