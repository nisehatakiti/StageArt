<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Application\Shared\UniqueConstraintViolationException;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantInvitationRepository;
use StageArt\Tests\Support\FakeMysqli;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 R14-B stage 2 (schema 1.49.0, design doc §5.1 / §6.1 / §7.4): the
 * response, close and resend columns are stored and read back as the
 * Domain holds them; pre-R14-B rows (CONSUMED, CANCELLED without a reason)
 * read back unchanged; token_hash is never changed here; the send /
 * notification columns are left alone; a failed write is never ignored
 * and its exception carries no address or token hash.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressParticipantInvitationRepositoryTest extends TestCase
{
    private const ID = '44444444-4444-4444-8444-444444444444';
    private const PRODUCTION = '55555555-5555-4555-8555-555555555555';
    private const INVITER = '66666666-6666-4666-8666-666666666666';
    private const INVITEE = '77777777-7777-4777-8777-777777777777';
    private const PARTICIPANT = '88888888-8888-4888-8888-888888888888';
    private const MANAGER = '99999999-9999-4999-8999-999999999999';
    private const EMAIL = 'invitee@example.com';
    private const TOKEN_HASH = 'f00df00df00df00df00df00df00df00df00df00df00df00df00df00df00df00d';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        require_once __DIR__ . '/../../../Support/fake-mysqli.php';
        FakeMysqli::$errno = 0;
    }

    private function repository(callable $responder): WordPressParticipantInvitationRepository
    {
        return new WordPressParticipantInvitationRepository(new \wpdb($responder));
    }

    private function pending(string $expiresAt = '2026-11-25 03:00:00', int $resendCount = 0): ParticipantInvitation
    {
        return ParticipantInvitation::reconstitute(
            ParticipantInvitationId::fromString(self::ID),
            ProductionId::fromString(self::PRODUCTION),
            self::EMAIL,
            'Invitee Name',
            PersonId::fromString(self::INVITER),
            ParticipantType::cast(),
            null,
            self::TOKEN_HASH,
            ParticipantInvitationStatus::pending(),
            new DateTimeImmutable('2026-11-18 03:00:00+00:00'),
            new DateTimeImmutable($expiresAt . '+00:00'),
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

    /**
     * @param array<string, string|int|null> $overrides
     * @return array<string, string|int|null>
     */
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'id' => self::ID,
            'production_id' => self::PRODUCTION,
            'email' => self::EMAIL,
            'invited_name' => 'Invitee Name',
            'invited_by_person_id' => self::INVITER,
            'participant_type' => 'CAST',
            'remarks' => null,
            'token_hash' => self::TOKEN_HASH,
            'status' => 'PENDING',
            'created_at' => '2026-11-18 03:00:00',
            'expires_at' => '2026-11-25 03:00:00',
            'consumed_at' => null,
            'invitee_person_id' => null,
            'responded_at' => null,
            'participant_id' => null,
            'closed_at' => null,
            'closed_by_person_id' => null,
            'close_reason' => null,
            'revocation_notified_at' => null,
            'resend_count' => '0',
            'current_send_id' => null,
            'last_sent_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function saved(ParticipantInvitation $invitation, bool $existing = false): array
    {
        $write = null;
        $repository = $this->repository(function (string $method, array $args) use (&$write, $existing) {
            if ($method === 'get_var') {
                return $existing ? self::ID : null;
            }

            if ($method === 'insert' || $method === 'update') {
                $write = [$method, $args[0], $args[1]];
            }

            return FakeWpdbDefault::VALUE;
        });

        $repository->save($invitation);
        $this->assertNotNull($write, 'One write was issued');

        return $write;
    }

    private function hydrated(array $row): ParticipantInvitation
    {
        $repository = $this->repository(static fn (string $method) => $method === 'get_row' ? $row : FakeWpdbDefault::VALUE);
        $invitation = $repository->findById(ParticipantInvitationId::fromString(self::ID));
        $this->assertNotNull($invitation);

        return $invitation;
    }

    public function test_an_accepted_invitation_is_stored_with_its_response(): void
    {
        $invitation = $this->pending();
        $invitation->accept(PersonId::fromString(self::INVITEE), ParticipantId::fromString(self::PARTICIPANT), new DateTimeImmutable('2026-11-20T10:00:00+00:00'));

        [$method, $table, $row] = $this->saved($invitation, true);

        $this->assertSame('update', $method);
        $this->assertSame('wp_stageart_participant_invitations', $table);
        $this->assertSame('ACCEPTED', $row['status']);
        $this->assertSame(self::INVITEE, $row['invitee_person_id']);
        $this->assertSame('2026-11-20 10:00:00', $row['responded_at']);
        $this->assertSame(self::PARTICIPANT, $row['participant_id']);
        $this->assertNull($row['close_reason']);
        $this->assertNull($row['closed_at']);
        $this->assertNull($row['closed_by_person_id']);
    }

    public function test_an_accepted_row_reads_back(): void
    {
        $invitation = $this->hydrated($this->row([
            'status' => 'ACCEPTED',
            'invitee_person_id' => self::INVITEE,
            'responded_at' => '2026-11-20 10:00:00',
            'participant_id' => self::PARTICIPANT,
        ]));

        $this->assertSame('ACCEPTED', $invitation->status()->toString());
        $this->assertSame(self::INVITEE, $invitation->inviteePersonId()->toString());
        $this->assertEquals(new DateTimeImmutable('2026-11-20T10:00:00+00:00'), $invitation->respondedAt());
        $this->assertSame(self::PARTICIPANT, $invitation->participantId()->toString());
    }

    public function test_a_rejected_invitation_is_stored_and_reads_back(): void
    {
        $invitation = $this->pending();
        $invitation->reject(PersonId::fromString(self::INVITEE), null, new DateTimeImmutable('2026-11-21T00:00:00+00:00'));

        [, , $row] = $this->saved($invitation, true);

        $this->assertSame('REJECTED', $row['status']);
        $this->assertSame(self::INVITEE, $row['invitee_person_id']);
        $this->assertSame('2026-11-21 00:00:00', $row['responded_at']);
        $this->assertNull($row['participant_id']);

        $read = $this->hydrated($this->row(['id' => self::ID] + $row));
        $this->assertSame('REJECTED', $read->status()->toString());
        $this->assertSame(self::INVITEE, $read->inviteePersonId()->toString());
        $this->assertEquals(new DateTimeImmutable('2026-11-21T00:00:00+00:00'), $read->respondedAt());
        $this->assertNull($read->participantId());
    }

    public function test_a_manager_close_is_stored_with_reason_time_and_actor_and_reads_back(): void
    {
        $invitation = $this->pending();
        $invitation->close(ParticipantInvitationCloseReason::manager(), PersonId::fromString(self::MANAGER), new DateTimeImmutable('2026-11-19T05:00:00+00:00'));

        [, , $row] = $this->saved($invitation, true);

        $this->assertSame('CANCELLED', $row['status']);
        $this->assertSame('MANAGER', $row['close_reason']);
        $this->assertSame('2026-11-19 05:00:00', $row['closed_at']);
        $this->assertSame(self::MANAGER, $row['closed_by_person_id']);

        $read = $this->hydrated($this->row(['id' => self::ID] + $row));
        $this->assertSame('CANCELLED', $read->status()->toString());
        $this->assertSame('MANAGER', $read->closeReason()->toString());
        $this->assertEquals(new DateTimeImmutable('2026-11-19T05:00:00+00:00'), $read->closedAt());
        $this->assertSame(self::MANAGER, $read->closedByPersonId()->toString());
    }

    /** A system close then the invitee's reject: REJECTED keeps the original reason, without an actor. */
    public function test_a_rejection_after_a_system_close_keeps_the_reason(): void
    {
        $read = $this->hydrated($this->row([
            'status' => 'REJECTED',
            'close_reason' => 'INVITER_REVOKED',
            'closed_at' => '2026-11-19 05:00:00',
            'invitee_person_id' => self::INVITEE,
            'responded_at' => '2026-11-19 06:00:00',
        ]));

        $this->assertSame('REJECTED', $read->status()->toString());
        $this->assertSame('INVITER_REVOKED', $read->closeReason()->toString());
        $this->assertNull($read->closedByPersonId());

        [, , $row] = $this->saved($read, true);
        $this->assertSame('INVITER_REVOKED', $row['close_reason']);
        $this->assertSame('2026-11-19 05:00:00', $row['closed_at']);
        $this->assertNull($row['closed_by_person_id']);
    }

    /** A pre-R14-B CANCELLED row has no close reason; it reads back as such. */
    public function test_a_legacy_cancelled_row_without_a_reason_reads_back(): void
    {
        $read = $this->hydrated($this->row(['status' => 'CANCELLED']));

        $this->assertSame('CANCELLED', $read->status()->toString());
        $this->assertNull($read->closeReason());
        $this->assertNull($read->closedAt());
        $this->assertNull($read->closedByPersonId());

        // Nothing is derived from the missing reason: the Domain still refuses a rejection.
        $this->expectException(\InvalidArgumentException::class);
        $read->reject(PersonId::fromString(self::INVITEE), null, new DateTimeImmutable('2026-11-19T00:00:00+00:00'));
    }

    /** A pre-R14-B CONSUMED row reads back as CONSUMED, its consumed_at and token hash as they were. */
    public function test_a_legacy_consumed_row_reads_back(): void
    {
        $read = $this->hydrated($this->row([
            'status' => 'CONSUMED',
            'consumed_at' => '2026-11-18 04:00:00',
            'expires_at' => '2026-11-19 03:00:00',
        ]));

        $this->assertSame('CONSUMED', $read->status()->toString());
        $this->assertEquals(new DateTimeImmutable('2026-11-18T04:00:00+00:00'), $read->consumedAt());
        $this->assertEquals(new DateTimeImmutable('2026-11-19T03:00:00+00:00'), $read->expiresAt());
        $this->assertSame(self::TOKEN_HASH, $read->tokenHash());
        $this->assertNull($read->inviteePersonId());
        $this->assertSame(0, $read->resendCount());

        [, , $row] = $this->saved($read, true);
        $this->assertSame('CONSUMED', $row['status']);
        $this->assertSame('2026-11-18 04:00:00', $row['consumed_at']);
        $this->assertSame('2026-11-19 03:00:00', $row['expires_at']);
    }

    /** A pre-R14-B PENDING row: every new column NULL, resend_count 0, the 24-hour expiry kept. */
    public function test_a_legacy_pending_row_reads_back_with_nulls(): void
    {
        $read = $this->hydrated($this->row(['expires_at' => '2026-11-19 03:00:00']));

        $this->assertSame('PENDING', $read->status()->toString());
        $this->assertEquals(new DateTimeImmutable('2026-11-19T03:00:00+00:00'), $read->expiresAt());
        $this->assertNull($read->inviteePersonId());
        $this->assertNull($read->respondedAt());
        $this->assertNull($read->participantId());
        $this->assertNull($read->closeReason());
        $this->assertNull($read->closedAt());
        $this->assertNull($read->closedByPersonId());
        $this->assertSame(0, $read->resendCount());
    }

    public function test_the_resend_count_is_stored_and_reads_back(): void
    {
        $invitation = $this->pending('2026-11-25 03:00:00', 2);
        $invitation->resend(new DateTimeImmutable('2026-11-22T00:00:00+00:00'));

        [, , $row] = $this->saved($invitation, true);

        $this->assertSame(3, $row['resend_count']);
        $this->assertSame('2026-11-29 00:00:00', $row['expires_at']);

        $read = $this->hydrated($this->row(['resend_count' => '3']));
        $this->assertSame(3, $read->resendCount());
        $this->assertSame(2, $read->remainingResends());
    }

    /** token_hash is written as the Domain holds it: a resend keeps it; the repository never makes one. */
    public function test_the_token_hash_is_not_changed(): void
    {
        $invitation = $this->pending();
        $invitation->resend(new DateTimeImmutable('2026-11-22T00:00:00+00:00'));

        [, , $update] = $this->saved($invitation, true);
        [, , $insert] = $this->saved($this->pending());

        $this->assertSame(self::TOKEN_HASH, $update['token_hash']);
        $this->assertSame(self::TOKEN_HASH, $insert['token_hash']);
    }

    /** The send / notification columns are not this step's: never written, so an UPDATE keeps them. */
    public function test_the_send_and_notification_columns_are_left_alone(): void
    {
        foreach ([false, true] as $existing) {
            [, , $row] = $this->saved($this->pending(), $existing);

            foreach (['revocation_notified_at', 'current_send_id', 'last_sent_at', 'updated_at'] as $column) {
                $this->assertArrayNotHasKey($column, $row);
            }
        }
    }

    public function test_a_new_invitation_is_inserted_with_its_id_and_creation_time(): void
    {
        [$method, , $row] = $this->saved($this->pending());

        $this->assertSame('insert', $method);
        $this->assertSame(self::ID, $row['id']);
        $this->assertSame('2026-11-18 03:00:00', $row['created_at']);
        $this->assertSame('PENDING', $row['status']);
        $this->assertSame(0, $row['resend_count']);
        $this->assertNull($row['invitee_person_id']);
        $this->assertNull($row['close_reason']);
    }

    /** Date-times are stored in UTC. */
    public function test_date_times_are_stored_in_utc(): void
    {
        $invitation = $this->pending();
        $invitation->accept(PersonId::fromString(self::INVITEE), ParticipantId::fromString(self::PARTICIPANT), new DateTimeImmutable('2026-11-20T19:00:00+09:00'));

        [, , $row] = $this->saved($invitation, true);

        $this->assertSame('2026-11-20 10:00:00', $row['responded_at']);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function writes(): array
    {
        return ['insert' => [false], 'update' => [true]];
    }

    /**
     * @dataProvider writes
     */
    public function test_a_failed_write_throws_without_the_address_or_token_hash(bool $existing): void
    {
        $repository = $this->repository(function (string $method, array $args, FakeWpdb $db) use ($existing) {
            if ($method === 'get_var') {
                return $existing ? self::ID : null;
            }

            if ($method === 'insert' || $method === 'update') {
                $db->dbh = FakeMysqli::handle();
                FakeMysqli::$errno = 1406;
                $db->last_error = "Data too long for column 'email' ('" . self::EMAIL . "')";

                return false;
            }

            return FakeWpdbDefault::VALUE;
        });

        try {
            $repository->save($this->pending());
            $this->fail('Expected PersistenceException');
        } catch (PersistenceException $exception) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $exception);
            $this->assertSame(1406, $exception->mysqlErrno());
            $this->assertStringNotContainsString(self::EMAIL, $exception->getMessage());
            $this->assertStringNotContainsString('Data too long', $exception->getMessage());
        }
    }

    /**
     * @dataProvider writes
     */
    public function test_a_unique_key_violation_is_reported_as_such_without_the_token_hash(bool $existing): void
    {
        $repository = $this->repository(function (string $method, array $args, FakeWpdb $db) use ($existing) {
            if ($method === 'get_var') {
                return $existing ? self::ID : null;
            }

            if ($method === 'insert' || $method === 'update') {
                $db->dbh = FakeMysqli::handle();
                FakeMysqli::$errno = 1062;
                $db->last_error = "Duplicate entry '" . self::TOKEN_HASH . "' for key 'token_hash'";

                return false;
            }

            return FakeWpdbDefault::VALUE;
        });

        try {
            $repository->save($this->pending());
            $this->fail('Expected UniqueConstraintViolationException');
        } catch (UniqueConstraintViolationException $exception) {
            $this->assertStringNotContainsString(self::TOKEN_HASH, $exception->getMessage());
        }
    }

    public function test_an_update_that_changes_no_row_is_not_a_failure(): void
    {
        $repository = $this->repository(static function (string $method) {
            if ($method === 'get_var') {
                return self::ID;
            }

            return $method === 'update' ? 0 : FakeWpdbDefault::VALUE;
        });

        $repository->save($this->pending());

        $this->addToAssertionCount(1);
    }

    public function test_a_failed_existence_read_throws_and_writes_nothing(): void
    {
        $writes = 0;
        $repository = $this->repository(function (string $method, array $args, FakeWpdb $db) use (&$writes) {
            if ($method === 'get_var') {
                $db->last_error = 'MySQL server has gone away';

                return null;
            }

            if ($method === 'insert' || $method === 'update') {
                $writes++;
            }

            return FakeWpdbDefault::VALUE;
        });

        try {
            $repository->save($this->pending());
            $this->fail('Expected PersistenceException');
        } catch (PersistenceException $exception) {
            $this->assertSame(0, $writes);
        }
    }
}
