<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\ListParticipantInvitationsQuery;
use StageArt\Application\ParticipantInvitation\ManagedParticipantInvitationResult;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationSendLimitException;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationCommand;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * PR #7 R14-B stage 4 remainder (R14B-4R, design doc §5.1 / §5.3 / §7.1 /
 * §11.3): the §7.1 fields of the managers'
 * `GET /productions/{id}/participant-invitations`, with the four decisions
 * of 2026-11-18 (missing key -> send_limit null; stored
 * CANCELLED(INVITER_REVOKED) -> INVITER_REVOKED; no current send ->
 * send_status and last_sent_at null). reinvite_available_at was removed
 * with the rejection (invitation policy 2026-11-27); past ACCEPTED /
 * REJECTED rows are still listed as history.
 */
final class ListParticipantInvitationsManagedFieldsTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
    }

    /**
     * @return array<string, ManagedParticipantInvitationResult> keyed by invitation id
     */
    private function listAs(?Person $as = null, ?Production $production = null): array
    {
        $rows = [];

        foreach ($this->f->listInvitations()->execute(new ListParticipantInvitationsQuery(
            ($production ?? $this->f->production)->id()->toString(),
            ($as ?? $this->f->primaryManager)->wordPressUserId()
        )) as $row) {
            $rows[$row->invitation->id] = $row;
        }

        return $rows;
    }

    private function row(ParticipantInvitation $invitation): array
    {
        return $this->listAs()[$invitation->id()->toString()]->toArray();
    }

    private function saved(ParticipantInvitation $invitation): ParticipantInvitation
    {
        $this->f->invitations->save($invitation);

        return $invitation;
    }

    private function ago(string $spec): DateTimeImmutable
    {
        return (new DateTimeImmutable())->sub(new DateInterval($spec));
    }

    /** A send to $email accepted at $at under key version $version, optionally made the invitation's current attempt. */
    private function send(string $email, DateTimeImmutable $at, string $status = ParticipantInvitationSend::SENT, string $version = 'v1', ?ParticipantInvitation $current = null, ?Production $production = null): ParticipantInvitationSend
    {
        $send = ParticipantInvitationSend::accept(
            $current !== null ? $current->id() : ParticipantInvitationId::generate(),
            ($production ?? $this->f->production)->id(),
            hash_hmac('sha256', mb_strtolower(trim($email)), $this->f->emailKeys[$version]),
            $version,
            ParticipantInvitationSend::KIND_INITIAL,
            $this->f->primaryManager->id(),
            $at
        );
        $this->f->sends->seed($send, $status);

        if ($current !== null) {
            $current->recordSendAccepted($send->id(), $at);
            $this->saved($current);
        }

        return $send;
    }

    private function create(string $email = self::EMAIL, string $type = ParticipantType::CAST): string
    {
        return $this->f->createInvitation()->execute(new CreateParticipantInvitationCommand(
            $this->f->production->id()->toString(),
            $this->f->primaryManager->wordPressUserId(),
            '招待 太郎',
            $email,
            $type,
            null
        ))->invitation->id;
    }

    // --- §5.1 effective_status -------------------------------------------------

    /**
     * @return array<string, array{0: callable, 1: string, 2: ?string, 3: bool, 4: bool}>
     *   [arrange(fixture, invitation): void, effective_status, close_reason, closed_at set, responded_at set]
     */
    public static function storedStates(): array
    {
        $invitee = static fn (ParticipantConsentFixture $f): Person => $f->person('invitee' . random_int(1, PHP_INT_MAX) . '@example.com');

        return [
            'PENDING within its expiry' => [static function (): void {
            }, 'PENDING', null, false, false],
            'CANCELLED(MANAGER)' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => $i->close(ParticipantInvitationCloseReason::manager(), $f->primaryManager->id()), 'CANCELLED', 'MANAGER', true, false],
            'CANCELLED(PRODUCTION_CLOSED)' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => $i->close(ParticipantInvitationCloseReason::productionClosed()), 'CANCELLED', 'PRODUCTION_CLOSED', true, false],
            'CANCELLED(INVITER_REVOKED) is shown as INVITER_REVOKED (§11.5)' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => $i->close(ParticipantInvitationCloseReason::inviterRevoked()), 'INVITER_REVOKED', 'INVITER_REVOKED', true, false],
            'CANCELLED without reason (pre-R14-B)' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => $i->cancel(), 'CANCELLED', null, false, false],
            'ACCEPTED (a past R14-B row)' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => self::answered($i, ParticipantInvitationStatus::ACCEPTED), 'ACCEPTED', null, false, true],
            'REJECTED (a past R14-B row)' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => self::answered($i, ParticipantInvitationStatus::REJECTED), 'REJECTED', null, false, true],
            'REJECTED first detecting PRODUCTION_CLOSED (a past R14-B row)' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => self::answered($i, ParticipantInvitationStatus::REJECTED, ParticipantInvitationCloseReason::productionClosed()), 'REJECTED', 'PRODUCTION_CLOSED', true, true],
            'COMPLETED by the registration (CONSUMED)' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => $i->completeByEnrollment($invitee($f)->id(), ParticipantId::generate()), 'CONSUMED', null, false, false],
            'CONSUMED' => [static fn (ParticipantConsentFixture $f, ParticipantInvitation $i) => $i->consume(), 'CONSUMED', null, false, false],
        ];
    }

    /**
     * @dataProvider storedStates
     */
    public function test_each_stored_state_has_its_effective_status_and_close_fields(callable $arrange, string $effective, ?string $closeReason, bool $closed, bool $responded): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $storedStatus = $invitation->status()->toString();
        $arrange($this->f, $invitation);
        $this->saved($invitation);

        $row = $this->row($invitation);

        $this->assertSame($effective, $row['effective_status']);
        $this->assertSame($closeReason, $row['close_reason']);
        $this->assertSame($closed, $row['closed_at'] !== null);
        $this->assertSame($responded, $row['responded_at'] !== null);
        $this->assertSame($invitation->status()->toString(), $row['status'], 'status stays the stored status');
        $this->assertNotSame('', $storedStatus);
    }

    public function test_the_derived_states_follow_the_order_expired_closed_revoked(): void
    {
        $manager = $this->f->participantManager();
        $pending = $this->f->invitation('a@example.com');
        $expired = $this->f->invitation('b@example.com', null, ParticipantType::CAST, $this->ago('PT1M'));
        $revoked = $this->f->invitation('c@example.com', $manager);
        $expiredRevoked = $this->f->invitation('d@example.com', $manager, ParticipantType::CAST, $this->ago('PT1M'));

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $delegate) {
            $delegate->deactivate($this->f->primaryManager->id());
        }

        $rows = $this->listAs();
        $this->assertSame('PENDING', $rows[$pending->id()->toString()]->effectiveStatus);
        $this->assertSame('EXPIRED', $rows[$expired->id()->toString()]->effectiveStatus);
        $this->assertSame('INVITER_REVOKED', $rows[$revoked->id()->toString()]->effectiveStatus);
        $this->assertSame('EXPIRED', $rows[$expiredRevoked->id()->toString()]->effectiveStatus, 'EXPIRED first');

        $this->f->forceProductionStatus(ProductionStatus::COMPLETED);
        $rows = $this->listAs();
        $this->assertSame('PRODUCTION_CLOSED', $rows[$pending->id()->toString()]->effectiveStatus);
        $this->assertSame('PRODUCTION_CLOSED', $rows[$revoked->id()->toString()]->effectiveStatus, 'PRODUCTION_CLOSED before INVITER_REVOKED');
        $this->assertSame('EXPIRED', $rows[$expired->id()->toString()]->effectiveStatus, 'EXPIRED before PRODUCTION_CLOSED');
    }

    /** @return array<string, array{0: string}> */
    public static function closedProductionStatuses(): array
    {
        return [
            'COMPLETED' => [ProductionStatus::COMPLETED],
            'ARCHIVED' => [ProductionStatus::ARCHIVED],
            'CANCELLED' => [ProductionStatus::CANCELLED],
        ];
    }

    /**
     * @dataProvider closedProductionStatuses
     */
    public function test_every_closed_production_status_derives_production_closed(string $status): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->forceProductionStatus($status);

        $this->assertSame('PRODUCTION_CLOSED', $this->row($invitation)['effective_status']);
    }

    public function test_the_list_is_a_read_that_writes_locks_and_notifies_nothing(): void
    {
        $manager = $this->f->participantManager();
        $invitation = $this->f->invitation(self::EMAIL, $manager);

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $delegate) {
            $delegate->deactivate($this->f->primaryManager->id());
        }

        $this->f->lockLog->reset();
        $saves = $this->f->invitations->saves;
        $before = clone $this->f->invitations->findById($invitation->id());

        $this->assertSame('INVITER_REVOKED', $this->row($invitation)['effective_status']);

        $this->assertSame($saves, $this->f->invitations->saves, 'Nothing saved');
        $this->assertEquals($before, $this->f->invitations->findById($invitation->id()), 'No close_reason written by a read');
        $this->assertNull($this->f->invitations->revocationNotifiedAt($invitation->id()));
        $this->assertSame([], $this->f->lockLog->order(), 'No lock (§5.4)');
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame([], $this->f->notificationTypes());
        $this->assertSame([], $this->f->sends->all());
    }

    // --- existing fields ----------------------------------------------------

    public function test_existing_fields_are_kept_and_no_invitee_identity_is_added(): void
    {
        $id = $this->create();
        $row = $this->listAs()[$id]->toArray();

        foreach (['id', 'production_id', 'email', 'name', 'invited_by_person_id', 'participant_type', 'remarks', 'status', 'created_at', 'expires_at', 'consumed_at', 'is_expired'] as $field) {
            $this->assertArrayHasKey($field, $row);
        }

        $this->assertSame(self::EMAIL, $row['email']);
        $this->assertArrayNotHasKey('invitee_person_id', $row);
        $this->assertArrayNotHasKey('participant_id', $row);
        $this->assertArrayNotHasKey('invitee_name', $row);
    }

    // --- send status (§6.2 / §11.3) ------------------------------------------

    public function test_a_created_invitation_shows_its_send_as_sent_and_a_failed_one_as_failed(): void
    {
        $sent = $this->create('a@example.com');
        $this->f->mailer->accepts = false;
        $failed = $this->create('b@example.com');

        $rows = $this->listAs();
        $this->assertSame('SENT', $rows[$sent]->sendStatus);
        $this->assertNotNull($rows[$sent]->lastSentAt);
        $this->assertSame('FAILED', $rows[$failed]->sendStatus);
    }

    public function test_a_pending_send_becomes_unknown_after_ten_minutes(): void
    {
        $fresh = $this->f->invitation('a@example.com');
        $stale = $this->f->invitation('b@example.com');
        $this->send('a@example.com', $this->ago('PT9M'), ParticipantInvitationSend::PENDING, 'v1', $fresh);
        $this->send('b@example.com', $this->ago('PT10M'), ParticipantInvitationSend::PENDING, 'v1', $stale);

        $rows = $this->listAs();
        $this->assertSame('PENDING', $rows[$fresh->id()->toString()]->sendStatus);
        $this->assertSame('UNKNOWN', $rows[$stale->id()->toString()]->sendStatus, 'T72: 10 minutes -> UNKNOWN');
    }

    public function test_the_status_is_the_current_attempts_not_an_older_one(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $old = $this->send(self::EMAIL, $this->ago('PT1H'), ParticipantInvitationSend::PENDING, 'v1', $invitation);
        $this->send(self::EMAIL, $this->ago('PT1M'), ParticipantInvitationSend::PENDING, 'v1', $invitation);
        $this->f->sends->completeIfPending($old->id(), ParticipantInvitationSend::FAILED, new DateTimeImmutable());

        $row = $this->row($invitation);
        $this->assertSame('PENDING', $row['send_status'], 'T73: an older result never shows');
        $this->assertSame(2, $row['send_limit']['sent_in_30_days']);
    }

    public function test_an_invitation_without_a_recorded_send_has_no_send_status_or_time(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);

        $row = $this->row($invitation);
        $this->assertNull($row['send_status']);
        $this->assertNull($row['last_sent_at']);
    }

    // --- resend_count / resend_remaining -------------------------------------

    public function test_resends_are_counted_down_to_zero(): void
    {
        $id = $this->create();
        $this->assertSame([0, 5], [$this->listAs()[$id]->resendCount, $this->listAs()[$id]->resendRemaining]);

        for ($i = 1; $i <= 5; $i++) {
            $this->f->resendInvitation()->execute(new ResendParticipantInvitationCommand($id, $this->f->primaryManager->wordPressUserId()));
            $row = $this->listAs()[$id];
            $this->assertSame([$i, 5 - $i], [$row->resendCount, $row->resendRemaining]);
        }

        $this->assertSame(6, $row->sendLimit->sentIn30Days);
    }

    // --- send_limit (§5.3 / §6.7) ------------------------------------------------

    public function test_the_send_limit_counts_every_send_to_the_address_in_30_days(): void
    {
        $id = $this->create();
        $limit = $this->listAs()[$id]->toArray()['send_limit'];
        $this->assertSame(['sent_in_30_days' => 1, 'remaining_in_30_days' => 5, 'available_at' => null], $limit);
    }

    public function test_the_limit_and_available_at_match_the_create_api(): void
    {
        foreach (['P29D', 'P20D', 'P10D', 'P5D', 'P2D'] as $daysAgo) {
            $this->send(self::EMAIL, $this->ago($daysAgo));
        }

        $id = $this->create(self::EMAIL, ParticipantType::CAST);
        $row = $this->listAs()[$id]->toArray();
        $this->assertSame(6, $row['send_limit']['sent_in_30_days']);
        $this->assertSame(0, $row['send_limit']['remaining_in_30_days']);

        try {
            $this->create(self::EMAIL, ParticipantType::STAFF);
            $this->fail('429 send_limit expected.');
        } catch (ParticipantInvitationSendLimitException $e) {
            $this->assertSame($e->availableAt()->format(DATE_ATOM), $row['send_limit']['available_at'], 'Same available_at as the 429');
        }
    }

    public function test_five_sends_leave_one(): void
    {
        foreach (['P20D', 'P10D', 'P5D', 'P2D'] as $daysAgo) {
            $this->send(self::EMAIL, $this->ago($daysAgo));
        }

        $id = $this->create();
        $this->assertSame(['sent_in_30_days' => 5, 'remaining_in_30_days' => 1, 'available_at' => null], $this->listAs()[$id]->toArray()['send_limit']);
    }

    public function test_the_window_edge_and_other_productions(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->send(self::EMAIL, $this->ago('P29DT23H59M'));
        $this->send(self::EMAIL, $this->ago('P30DT1M'));
        $other = Production::create($this->f->production->projectId(), new ProductionName('Other'), $this->f->primaryManager->id());
        $this->f->productions->save($other);
        $this->send(self::EMAIL, $this->ago('P1D'), ParticipantInvitationSend::SENT, 'v1', null, $other);

        $this->assertSame(1, $this->row($invitation)['send_limit']['sent_in_30_days'], 'T70 / T71');
    }

    public function test_case_types_and_key_versions_are_counted_together(): void
    {
        $this->f->emailKeys['v0'] = 'k0-0123456789abcdef0123456789abcdef';
        $cast = $this->f->invitation('Taro@Example.com', null, ParticipantType::CAST);
        $staff = $this->f->invitation('taro@example.com', null, ParticipantType::STAFF);
        $this->send('taro@example.com', $this->ago('P3D'), ParticipantInvitationSend::SENT, 'v0');
        $this->send('TARO@example.com ', $this->ago('P2D'));
        $this->send('hanako@example.com', $this->ago('P1D'));

        $rows = $this->listAs();
        $this->assertSame(2, $rows[$cast->id()->toString()]->sendLimit->sentIn30Days, 'T68 / T69 / §6.7');
        $this->assertSame(2, $rows[$staff->id()->toString()]->sendLimit->sentIn30Days);
    }

    public function test_a_missing_key_version_makes_the_limit_unknown_but_the_list_answers(): void
    {
        $this->f->emailKeys['v0'] = 'k0-0123456789abcdef0123456789abcdef';
        $invitation = $this->f->invitation(self::EMAIL);
        $this->send('someone@example.com', $this->ago('P3D'), ParticipantInvitationSend::SENT, 'v0');
        unset($this->f->emailKeys['v0']);

        $row = $this->row($invitation);
        $this->assertNull($row['send_limit'], 'Decided 2026-11-18: never a count that is too low');
        $this->assertSame('PENDING', $row['effective_status'], 'The other fields are still answered');
    }

    public function test_no_keyring_at_all_still_counts_when_no_version_is_in_the_window(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);
        $this->f->emailKeys = [];
        $this->f->currentEmailKey = null;

        $this->assertSame(['sent_in_30_days' => 0, 'remaining_in_30_days' => 6, 'available_at' => null], $this->row($invitation)['send_limit']);

        $this->f->emailKeys = ['v1' => 'k1-0123456789abcdef0123456789abcdef'];
        $this->send(self::EMAIL, $this->ago('P1D'));
        $this->f->currentEmailKey = null;
        $this->assertSame(1, $this->row($invitation)['send_limit']['sent_in_30_days'], 'The current version is not needed to count');
    }

    public function test_a_failed_send_read_throws_instead_of_counting_zero(): void
    {
        $this->f->invitation(self::EMAIL);
        $this->f->sends->failNextRead = new PersistenceException('select from', 'wp_stageart_participant_invitation_sends', 1205);

        $this->expectException(PersistenceException::class);
        $this->listAs();
    }

    // --- reinvite_available_at (D6, removed 2026-11-27): see test_a_past_rejection_is_listed_without_a_reinvite_time_and_does_not_block ---

    // --- authorization -------------------------------------------------------

    public function test_a_participant_manager_may_list(): void
    {
        $invitation = $this->f->invitation(self::EMAIL);

        $this->assertArrayHasKey($invitation->id()->toString(), $this->listAs($this->f->participantManager()));
    }

    public function test_someone_without_the_role_or_a_manager_of_another_production_is_refused(): void
    {
        $this->f->invitation(self::EMAIL);
        $other = Production::create($this->f->production->projectId(), new ProductionName('Other'), $this->f->person('other-pm@example.com')->id());
        $this->f->productions->save($other);
        $otherManager = $this->f->people->findById($other->primaryManagerPersonId());

        foreach ([$this->f->person('someone@example.com'), $otherManager] as $outsider) {
            try {
                $this->listAs($outsider);
                $this->fail('403 expected.');
            } catch (ParticipantAccessDeniedException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_unknown_production_is_404(): void
    {
        $this->expectException(ProductionNotFoundException::class);
        $this->f->listInvitations()->execute(new ListParticipantInvitationsQuery(ProductionId::generate()->toString(), $this->f->primaryManager->wordPressUserId()));
    }

    // --- no read per row -----------------------------------------------------------

    public function test_the_sends_are_read_twice_whatever_the_number_of_rows(): void
    {
        $this->f->invitation('one@example.com');
        $this->f->sends->recordReads = 0;
        $this->listAs();
        $one = $this->f->sends->recordReads;

        for ($i = 0; $i < 20; $i++) {
            $this->send("p{$i}@example.com", $this->ago('PT1H'), ParticipantInvitationSend::SENT, 'v1', $this->f->invitation("p{$i}@example.com"));
        }

        $this->f->sends->recordReads = 0;
        $this->assertCount(21, $this->listAs());
        $this->assertSame(2, $this->f->sends->recordReads, 'recordsSince + recordsByIds once each');
        $this->assertLessThanOrEqual(2, $one);
    }

    /**
     * Sets a past R14-B answer on the row as it is stored (the acceptance and
     * the rejection were removed with the 2026-11-27 invitation policy - such
     * rows can only come from the database now).
     */
    private static function answered(ParticipantInvitation $invitation, string $status, ?ParticipantInvitationCloseReason $detected = null): void
    {
        \Closure::bind(function () use ($status, $detected): void {
            $now = new DateTimeImmutable();
            $this->status = ParticipantInvitationStatus::fromString($status);
            $this->inviteePersonId = PersonId::generate();
            $this->respondedAt = $now;
            $this->updatedAt = $now;

            if ($status === ParticipantInvitationStatus::ACCEPTED) {
                $this->participantId = ParticipantId::generate();
            }

            if ($detected !== null) {
                $this->closeReason = $detected;
                $this->closedAt = $now;
            }
        }, $invitation, ParticipantInvitation::class)();
    }

    /** Invitation policy 2026-11-27: there is no reinvite_available_at any more (no block after a rejection); a past REJECTED row is still listed. */
    public function test_a_past_rejection_is_listed_without_a_reinvite_time_and_does_not_block(): void
    {
        $rejected = $this->f->invitation(self::EMAIL);
        self::answered($rejected, ParticipantInvitationStatus::REJECTED);
        $this->saved($rejected);

        $row = $this->row($rejected);

        $this->assertSame('REJECTED', $row['effective_status']);
        $this->assertArrayNotHasKey('reinvite_available_at', $row);
        $this->create(self::EMAIL, ParticipantType::STAFF);
        $this->addToAssertionCount(1);
    }

}
