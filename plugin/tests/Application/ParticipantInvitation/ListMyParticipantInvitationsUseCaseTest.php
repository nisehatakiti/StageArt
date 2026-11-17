<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\ParticipantInvitation\InvitationRecipientNotEligibleException;
use StageArt\Application\ParticipantInvitation\ListMyParticipantInvitationsQuery;
use StageArt\Application\ParticipantInvitation\MyParticipantInvitationResult;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §5.1 / §7.2 `GET /me/participant-invitations` (D7, N5, U7).
 */
final class ListMyParticipantInvitationsUseCaseTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;
    private Person $invitee;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->invitee = $this->f->person(self::EMAIL);
    }

    /**
     * @return MyParticipantInvitationResult[]
     */
    private function listFor(?Person $person = null): array
    {
        return $this->f->listMine()->execute(new ListMyParticipantInvitationsQuery(($person ?? $this->invitee)->wordPressUserId()));
    }

    /**
     * @param MyParticipantInvitationResult[] $items
     * @return string[]
     */
    private static function ids(array $items): array
    {
        return array_map(static fn (MyParticipantInvitationResult $item): string => $item->id, $items);
    }

    public function test_a_pending_invitation_is_listed_with_what_the_invitee_needs_only(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);

        $items = $this->listFor();

        $this->assertCount(1, $items);
        $item = $items[0]->toArray();
        $this->assertSame($invitation->id()->toString(), $item['id']);
        $this->assertSame('Autumn Play', $item['production_name']);
        $this->assertSame('Theatre Co', $item['organization_name']);
        $this->assertSame('CAST', $item['participant_type']);
        $this->assertSame('公演の管理者', $item['invited_by_display_name'], 'The PrimaryManager has no Production-side name here');
        $this->assertSame('PENDING', $item['effective_status']);
        $this->assertTrue($item['can_accept']);
        $this->assertTrue($item['can_reject']);
        $this->assertNull($item['unavailable_reason']);
        $this->assertFalse($item['already_participant']);

        $text = json_encode($item, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(self::EMAIL, $text);
        $this->assertStringNotContainsString('招待 太郎', $text, 'invited_name is not returned');
        $this->assertStringNotContainsString('internal memo', $text, 'remarks are not returned');
        $this->assertStringNotContainsString($invitation->tokenHash(), $text);
        $this->assertSame(
            ['id', 'production_id', 'production_name', 'organization_name', 'participant_type', 'invited_by_display_name', 'expires_at', 'effective_status', 'already_participant', 'can_accept', 'can_reject', 'unavailable_reason'],
            array_keys($item)
        );
        $this->assertSame(0, $this->f->invitations->tokenHashLookups);
    }

    public function test_the_inviters_production_side_name_is_shown(): void
    {
        $manager = $this->f->participantManager();
        $this->f->invitation(self::EMAIL, $manager);

        $this->assertSame('制作 花子', $this->listFor()[0]->invitedByDisplayName);
    }

    public function test_only_verified_addresses_are_used(): void
    {
        $person = $this->f->person('login@example.com');
        $this->f->notificationAddress($person, 'pending-change@example.com', false);
        $this->f->invitation('pending-change@example.com');
        $verified = $this->f->invitation('LOGIN@example.com');

        $this->assertSame([$verified->id()->toString()], self::ids($this->listFor($person)));
        $this->assertSame([['login@example.com']], $this->f->invitations->candidateSearches, 'Only verified addresses are searched');
    }

    public function test_a_user_with_no_verified_address_gets_an_empty_list(): void
    {
        $person = $this->f->person('late@example.com', false);
        $this->f->invitation('late@example.com');

        $this->assertSame([], $this->listFor($person));
        $this->assertSame([], $this->f->invitations->candidateSearches);
    }

    public function test_someone_elses_invitations_and_a_shared_address_are_not_listed(): void
    {
        $this->f->invitation('other@example.com');
        $this->f->person('other@example.com');
        $this->f->notificationAddress($this->f->person('hanako@example.com'), self::EMAIL, true);
        $this->f->invitation(self::EMAIL);

        $this->assertSame([], $this->listFor(), 'An address of two Persons is nobody\'s');
    }

    public function test_expired_and_answered_invitations_are_not_listed(): void
    {
        $this->f->invitation(self::EMAIL, null, ParticipantType::CAST, new DateTimeImmutable('-1 minute'));
        $accepted = $this->f->invitation(self::EMAIL);
        $accepted->accept($this->invitee->id(), \StageArt\Domain\Participant\ParticipantId::generate());
        $rejected = $this->f->invitation(self::EMAIL);
        $rejected->reject($this->invitee->id());
        $manager = $this->f->invitation(self::EMAIL);
        $manager->close(ParticipantInvitationCloseReason::manager(), $this->f->primaryManager->id());
        $legacy = $this->f->invitation(self::EMAIL);
        $legacy->cancel();
        $consumed = $this->f->invitation(self::EMAIL);
        $consumed->consume();
        $expiredClosed = $this->f->invitation(self::EMAIL, null, ParticipantType::CAST, new DateTimeImmutable('-1 minute'));
        $expiredClosed->close(ParticipantInvitationCloseReason::productionClosed());

        $this->assertSame([], $this->listFor());
    }

    public function test_a_closed_production_or_revoked_inviter_is_listed_for_rejection_only(): void
    {
        $manager = $this->f->participantManager();
        $revoked = $this->f->invitation(self::EMAIL, $manager);

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $row) {
            $row->deactivate($this->f->primaryManager->id());
        }

        $closedAfterAttempt = $this->f->invitation(self::EMAIL, null, ParticipantType::STAFF);
        $closedAfterAttempt->close(ParticipantInvitationCloseReason::productionClosed());

        $items = [];

        foreach ($this->listFor() as $item) {
            $items[$item->id] = $item;
        }

        $this->assertSame('INVITER_REVOKED', $items[$revoked->id()->toString()]->effectiveStatus);
        $this->assertSame('INVITER_REVOKED', $items[$revoked->id()->toString()]->unavailableReason);
        $this->assertFalse($items[$revoked->id()->toString()]->canAccept);
        $this->assertTrue($items[$revoked->id()->toString()]->canReject);

        $this->assertSame('CANCELLED', $items[$closedAfterAttempt->id()->toString()]->effectiveStatus);
        $this->assertSame('PRODUCTION_CLOSED', $items[$closedAfterAttempt->id()->toString()]->unavailableReason);
        $this->assertFalse($items[$closedAfterAttempt->id()->toString()]->canAccept);
        $this->assertTrue($items[$closedAfterAttempt->id()->toString()]->canReject);

        $this->assertSame(ParticipantInvitationCloseReason::PRODUCTION_CLOSED, $closedAfterAttempt->closeReason()->toString());
        $this->assertNull($this->f->invitations->findById($revoked->id())->closeReason(), 'A read never writes close_reason');
        $this->assertNull($this->f->invitations->revocationNotifiedAt($revoked->id()));
        $this->assertSame([], $this->f->notificationTypes(), 'A read never notifies');
        $this->assertSame([], $this->f->eventStore->types());
    }

    public function test_a_closed_production_is_derived(): void
    {
        $this->f->forceProductionStatus(ProductionStatus::ARCHIVED);
        $this->f->invitation(self::EMAIL);

        $item = $this->listFor()[0];

        $this->assertSame('PRODUCTION_CLOSED', $item->effectiveStatus);
        $this->assertFalse($item->canAccept);
        $this->assertTrue($item->canReject);
    }

    public function test_already_participant_is_the_invitees_own_active_row_of_that_type(): void
    {
        $this->f->participantRow($this->invitee, ParticipantStatus::ACTIVE, ParticipantType::CAST);
        $this->f->participantRow($this->invitee, ParticipantStatus::WITHDRAWN, ParticipantType::STAFF);
        $cast = $this->f->invitation(self::EMAIL, null, ParticipantType::CAST);
        $staff = $this->f->invitation(self::EMAIL, null, ParticipantType::STAFF);

        $items = [];

        foreach ($this->listFor() as $item) {
            $items[$item->id] = $item;
        }

        $this->assertTrue($items[$cast->id()->toString()]->alreadyParticipant);
        $this->assertFalse($items[$staff->id()->toString()]->alreadyParticipant);
    }

    public function test_a_suspended_account_cannot_list(): void
    {
        $person = $this->f->person('susp@example.com', true, 'SUSPENDED');

        $this->expectException(InvitationRecipientNotEligibleException::class);
        $this->listFor($person);
    }
}
