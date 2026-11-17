<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Application\Shared\UniqueConstraintViolationException;
use StageArt\Domain\JoinKey\JoinKeyId;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\Role\RoleKey;
use StageArt\Infrastructure\WordPress\Persistence\WordPressJoinKeyRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantEventRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantInvitationRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressProductionDelegateRepository;
use StageArt\Tests\Support\FakeMysqli;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 R14-B stage 3 (design doc §5.4 / §6.3 / §6.4 / §7.1 / §7.4 / §11.5):
 * the Repository additions the Application layer relies on.
 *
 * - Locking reads use SELECT ... FOR UPDATE and a failed read throws
 *   (PersistenceException, no database text) instead of reading as "none".
 * - The invitee's invitations are searched by SQL and kept only on an exact
 *   match of the §6.4 normalisation in PHP.
 * - revocation_notified_at and the Join Key use are conditional UPDATEs
 *   whose affected-row count decides; a failure throws.
 * - The delegate writes and the audit INSERT are checked.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class R14bStage3RepositoryTest extends TestCase
{
    private const ID = '11111111-1111-4111-8111-111111111111';
    private const PRODUCTION = '22222222-2222-4222-8222-222222222222';
    private const PERSON = '33333333-3333-4333-8333-333333333333';
    private const ACTOR = '44444444-4444-4444-8444-444444444444';
    private const SECRET_ERROR = "Duplicate entry 'taro@example.com' for key 'x'";

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        require_once __DIR__ . '/../../../Support/fake-mysqli.php';
        FakeMysqli::$errno = 0;
    }

    /**
     * @return array<string, string|null>
     */
    private function participantRow(array $overrides = []): array
    {
        return $overrides + [
            'id' => self::ID,
            'production_id' => self::PRODUCTION,
            'subject_type' => 'PERSON',
            'subject_id' => self::PERSON,
            'participant_type' => 'CAST',
            'status' => 'ACTIVE',
            'remarks' => null,
            'display_name' => null,
            'created_at' => '2026-11-01 10:00:00',
            'updated_at' => '2026-11-02 11:00:00',
            'enrollment_source' => null,
            'consent_source' => null,
            'consented_at' => null,
            'requested_at' => null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function invitationRow(array $overrides = []): array
    {
        return $overrides + [
            'id' => self::ID,
            'production_id' => self::PRODUCTION,
            'email' => 'taro@example.com',
            'invited_name' => 'Taro',
            'invited_by_person_id' => self::ACTOR,
            'participant_type' => 'CAST',
            'remarks' => null,
            'token_hash' => str_repeat('a', 64),
            'status' => 'PENDING',
            'created_at' => '2026-11-10 00:00:00',
            'expires_at' => '2026-11-17 00:00:00',
            'consumed_at' => null,
            'invitee_person_id' => null,
            'responded_at' => null,
            'participant_id' => null,
            'close_reason' => null,
            'closed_at' => null,
            'closed_by_person_id' => null,
            'resend_count' => '0',
            'revocation_notified_at' => null,
            'current_send_id' => null,
            'last_sent_at' => null,
            'updated_at' => null,
        ];
    }

    private static function failRead(\wpdb $db): void
    {
        $db->dbh = FakeMysqli::handle();
        FakeMysqli::$errno = 1205;
        $db->last_error = 'Lock wait timeout exceeded; try restarting transaction';
    }

    private static function assertNoDatabaseText(\Throwable $e): void
    {
        self::assertStringNotContainsString('example.com', $e->getMessage());
        self::assertStringNotContainsString('Lock wait', $e->getMessage());
        self::assertStringNotContainsString('Duplicate entry', $e->getMessage());
    }

    // --- Participant -------------------------------------------------

    public function test_participant_locking_reads_use_for_update_and_return_the_row(): void
    {
        $db = new \wpdb(fn (string $method) => $method === 'get_row' ? $this->participantRow() : FakeWpdbDefault::VALUE);
        $repository = new WordPressParticipantRepository($db);

        $byId = $repository->findByIdForUpdate(ParticipantId::fromString(self::ID));
        $byKey = $repository->findByProductionAndSubjectForUpdate(
            ProductionId::fromString(self::PRODUCTION),
            ParticipantSubjectType::person(),
            self::PERSON,
            ParticipantType::cast()
        );

        $this->assertSame(self::ID, $byId->id()->toString());
        $this->assertSame(self::ID, $byKey->id()->toString());

        foreach ($db->callsTo('get_row') as [$sql]) {
            $this->assertStringEndsWith('FOR UPDATE', $sql);
        }

        $this->assertStringContainsString("participant_type = 'CAST'", $db->callsTo('get_row')[1][0]);
    }

    public function test_participant_locking_reads_return_null_for_no_row(): void
    {
        $repository = new WordPressParticipantRepository(new \wpdb(static fn () => FakeWpdbDefault::VALUE));

        $this->assertNull($repository->findByIdForUpdate(ParticipantId::fromString(self::ID)));
        $this->assertNull($repository->findByProductionAndSubjectForUpdate(
            ProductionId::fromString(self::PRODUCTION),
            ParticipantSubjectType::person(),
            self::PERSON,
            ParticipantType::cast()
        ));
    }

    public function test_a_failed_participant_locking_read_throws_instead_of_reading_as_no_row(): void
    {
        $repository = new WordPressParticipantRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            if ($method === 'get_row') {
                self::failRead($db);

                return null;
            }

            return FakeWpdbDefault::VALUE;
        }));

        try {
            $repository->findByProductionAndSubjectForUpdate(
                ProductionId::fromString(self::PRODUCTION),
                ParticipantSubjectType::person(),
                self::PERSON,
                ParticipantType::cast()
            );
            $this->fail('A failed read must throw.');
        } catch (PersistenceException $e) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $e);
            $this->assertSame(1205, $e->mysqlErrno());
            self::assertNoDatabaseText($e);
        }

        $this->expectException(PersistenceException::class);
        $repository->findByIdForUpdate(ParticipantId::fromString(self::ID));
    }

    // --- Invitation --------------------------------------------------

    public function test_invitation_locking_read_uses_for_update_and_a_failure_throws(): void
    {
        $db = new \wpdb(fn (string $method) => $method === 'get_row' ? $this->invitationRow() : FakeWpdbDefault::VALUE);
        $found = (new WordPressParticipantInvitationRepository($db))->findByIdForUpdate(ParticipantInvitationId::fromString(self::ID));

        $this->assertSame(self::ID, $found->id()->toString());
        $this->assertStringEndsWith('FOR UPDATE', $db->callsTo('get_row')[0][0]);

        $failing = new WordPressParticipantInvitationRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            self::failRead($db);

            return null;
        }));

        $this->expectException(PersistenceException::class);
        $failing->findByIdForUpdate(ParticipantInvitationId::fromString(self::ID));
    }

    /** §6.4 / U9: the SQL candidates are kept only on an exact normalised match in PHP. */
    public function test_verified_email_candidates_are_matched_exactly_after_normalisation(): void
    {
        $rows = [
            $this->invitationRow(['id' => '11111111-1111-4111-8111-000000000001', 'email' => 'Taro@Example.COM']),
            $this->invitationRow(['id' => '11111111-1111-4111-8111-000000000002', 'email' => 'taro@example.com']),
            // What a _ci collation may also call equal - never a match here.
            $this->invitationRow(['id' => '11111111-1111-4111-8111-000000000003', 'email' => 'tarö@example.com']),
            $this->invitationRow(['id' => '11111111-1111-4111-8111-000000000004', 'email' => 'ｔａｒｏ@example.com']),
            $this->invitationRow(['id' => '11111111-1111-4111-8111-000000000005', 'email' => 'other@example.com']),
        ];
        $db = new \wpdb(static fn (string $method) => $method === 'get_results' ? $rows : FakeWpdbDefault::VALUE);

        $found = (new WordPressParticipantInvitationRepository($db))->findByVerifiedEmailCandidates(['  TARO@example.com ']);

        $this->assertSame(
            ['11111111-1111-4111-8111-000000000001', '11111111-1111-4111-8111-000000000002'],
            array_map(static fn (ParticipantInvitation $i): string => $i->id()->toString(), $found)
        );
        $this->assertStringContainsString("WHERE email IN ('taro@example.com')", $db->callsTo('get_results')[0][0]);
    }

    public function test_no_verified_address_searches_nothing(): void
    {
        $db = new \wpdb(static fn () => FakeWpdbDefault::VALUE);

        $this->assertSame([], (new WordPressParticipantInvitationRepository($db))->findByVerifiedEmailCandidates(['', '  ']));
        $this->assertSame([], $db->callsTo('get_results'));
    }

    public function test_a_failed_candidate_search_throws(): void
    {
        $repository = new WordPressParticipantInvitationRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            self::failRead($db);

            return [];
        }));

        $this->expectException(PersistenceException::class);
        $repository->findByVerifiedEmailCandidates(['taro@example.com']);
    }

    /** §11.5: the conditional UPDATE decides whether the one notification is attempted. */
    public function test_revocation_notification_is_marked_only_while_null(): void
    {
        $affected = 1;
        $db = new \wpdb(static function (string $method) use (&$affected) {
            return $method === 'query' ? $affected : FakeWpdbDefault::VALUE;
        });
        $repository = new WordPressParticipantInvitationRepository($db);
        $at = new DateTimeImmutable('2026-11-17T18:00:00+09:00');

        $this->assertTrue($repository->markRevocationNotificationAttempted(ParticipantInvitationId::fromString(self::ID), $at));
        $affected = 0;
        $this->assertFalse($repository->markRevocationNotificationAttempted(ParticipantInvitationId::fromString(self::ID), $at));

        $sql = $db->callsTo('query')[0][0];
        $this->assertStringContainsString("SET revocation_notified_at = '2026-11-17 09:00:00'", $sql);
        $this->assertStringContainsString('AND revocation_notified_at IS NULL', $sql);
    }

    public function test_a_failed_revocation_mark_throws(): void
    {
        $repository = new WordPressParticipantInvitationRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            if ($method === 'query') {
                $db->last_error = self::SECRET_ERROR;

                return false;
            }

            return FakeWpdbDefault::VALUE;
        }));

        try {
            $repository->markRevocationNotificationAttempted(ParticipantInvitationId::fromString(self::ID), new DateTimeImmutable());
            $this->fail('A failed write must throw.');
        } catch (PersistenceException $e) {
            self::assertNoDatabaseText($e);
        }
    }

    /** Stage 3: updated_at is the Domain's - written when set, NULL kept for an unchanged legacy row, read back. */
    public function test_updated_at_is_written_as_the_domain_holds_it(): void
    {
        $writes = [];
        $legacy = null;
        $db = new \wpdb(function (string $method, array $args) use (&$writes) {
            if ($method === 'get_var') {
                return self::ID;
            }

            if ($method === 'update') {
                $writes[] = $args[1];
            }

            if ($method === 'get_row') {
                return $this->invitationRow(['updated_at' => null]);
            }

            return FakeWpdbDefault::VALUE;
        });
        $repository = new WordPressParticipantInvitationRepository($db);

        $legacy = $repository->findById(ParticipantInvitationId::fromString(self::ID));
        $this->assertNull($legacy->updatedAt());
        $repository->save($legacy);
        $this->assertArrayHasKey('updated_at', $writes[0]);
        $this->assertNull($writes[0]['updated_at'], 'An unchanged legacy row keeps NULL; nothing is filled in');

        $legacy->reject(PersonId::fromString(self::PERSON), null, new DateTimeImmutable('2026-11-16T12:00:00+09:00'));
        $repository->save($legacy);
        $this->assertSame('2026-11-16 03:00:00', $writes[1]['updated_at']);

        $read = (new WordPressParticipantInvitationRepository(new \wpdb(fn (string $method) => $method === 'get_row'
            ? $this->invitationRow(['updated_at' => '2026-11-16 03:00:00', 'status' => ParticipantInvitationStatus::REJECTED, 'invitee_person_id' => self::PERSON, 'responded_at' => '2026-11-16 03:00:00'])
            : FakeWpdbDefault::VALUE)))->findById(ParticipantInvitationId::fromString(self::ID));
        $this->assertEquals(new DateTimeImmutable('2026-11-16T03:00:00+00:00'), $read->updatedAt());
    }

    // --- Join Key ----------------------------------------------------

    /** §7.1: one conditional UPDATE counts the use and keeps recordUse()'s EXHAUSTED rule. */
    public function test_join_key_use_is_one_conditional_update(): void
    {
        $affected = 1;
        $db = new \wpdb(static function (string $method) use (&$affected) {
            return $method === 'query' ? $affected : FakeWpdbDefault::VALUE;
        });
        $repository = new WordPressJoinKeyRepository($db);
        $id = JoinKeyId::fromString(self::ID);

        $this->assertTrue($repository->recordUseIfUsable($id, new DateTimeImmutable('2026-11-17T09:00:00+09:00')));
        $affected = 0;
        $this->assertFalse($repository->recordUseIfUsable($id, new DateTimeImmutable('2026-11-17T09:00:00+09:00')));

        $sql = $db->callsTo('query')[0][0];
        $this->assertMatchesRegularExpression(
            "/SET status = CASE WHEN max_uses IS NOT NULL AND use_count \\+ 1 >= max_uses THEN 'EXHAUSTED' ELSE status END, use_count = use_count \\+ 1 /",
            $sql
        );
        $this->assertStringContainsString("WHERE id = '" . self::ID . "' AND status = 'ACTIVE'", $sql);
        $this->assertStringContainsString('(max_uses IS NULL OR use_count < max_uses)', $sql);
        $this->assertStringContainsString("(expires_at IS NULL OR expires_at > '2026-11-17 00:00:00')", $sql);
        $this->assertSame([], $db->callsTo('update'), 'Not a read-modify-write save()');
    }

    public function test_a_failed_join_key_use_throws(): void
    {
        $repository = new WordPressJoinKeyRepository(new \wpdb(static fn (string $method) => $method === 'query' ? false : FakeWpdbDefault::VALUE));

        $this->expectException(PersistenceException::class);
        $repository->recordUseIfUsable(JoinKeyId::fromString(self::ID), new DateTimeImmutable());
    }

    // --- Delegates ---------------------------------------------------

    private function delegateRow(array $overrides = []): array
    {
        return $overrides + [
            'id' => self::ID,
            'production_id' => self::PRODUCTION,
            'person_id' => self::PERSON,
            'role' => 'PARTICIPANT_MANAGER',
            'status' => 'ACTIVE',
            'created_by' => self::ACTOR,
            'created_at' => '2026-11-01 00:00:00',
            'updated_by' => self::ACTOR,
            'updated_at' => '2026-11-01 00:00:00',
        ];
    }

    public function test_delegate_rows_of_a_person_are_locked_and_a_failed_read_throws(): void
    {
        $db = new \wpdb(fn (string $method) => $method === 'get_results' ? [$this->delegateRow()] : FakeWpdbDefault::VALUE);
        $rows = (new WordPressProductionDelegateRepository($db))->findByProductionAndPersonForUpdate(
            ProductionId::fromString(self::PRODUCTION),
            PersonId::fromString(self::PERSON)
        );

        $this->assertCount(1, $rows);
        $sql = $db->callsTo('get_results')[0][0];
        $this->assertStringContainsString("production_id = '" . self::PRODUCTION . "' AND person_id = '" . self::PERSON . "'", $sql);
        $this->assertStringEndsWith('FOR UPDATE', $sql);

        $failing = new WordPressProductionDelegateRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            self::failRead($db);

            return [];
        }));

        $this->expectException(PersistenceException::class);
        $failing->findByProductionAndPersonForUpdate(ProductionId::fromString(self::PRODUCTION), PersonId::fromString(self::PERSON));
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function existingOrNew(): array
    {
        return ['update' => [true], 'insert' => [false]];
    }

    /**
     * @dataProvider existingOrNew
     */
    public function test_a_failed_delegate_write_throws_and_1062_is_told_apart(bool $existing): void
    {
        $errno = 0;
        $make = static function () use ($existing, &$errno): WordPressProductionDelegateRepository {
            return new WordPressProductionDelegateRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) use ($existing, &$errno) {
                if ($method === 'get_var') {
                    return $existing ? self::ID : null;
                }

                if ($method === 'insert' || $method === 'update') {
                    $db->dbh = FakeMysqli::handle();
                    FakeMysqli::$errno = $errno;
                    $db->last_error = self::SECRET_ERROR;

                    return false;
                }

                return FakeWpdbDefault::VALUE;
            }));
        };
        $delegate = ProductionDelegate::create(
            ProductionId::fromString(self::PRODUCTION),
            PersonId::fromString(self::PERSON),
            RoleKey::participantManager(),
            PersonId::fromString(self::ACTOR)
        );

        $errno = 1062;

        try {
            $make()->save($delegate);
            $this->fail('A 1062 must throw.');
        } catch (UniqueConstraintViolationException $e) {
            self::assertNoDatabaseText($e);
        }

        $errno = 1146;

        try {
            $make()->save($delegate);
            $this->fail('A failed write must throw.');
        } catch (PersistenceException $e) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $e);
            $this->assertSame(1146, $e->mysqlErrno());
        }
    }

    public function test_a_failed_delegate_delete_throws(): void
    {
        $repository = new WordPressProductionDelegateRepository(new \wpdb(static fn (string $method) => $method === 'delete' ? false : FakeWpdbDefault::VALUE));

        $this->expectException(PersistenceException::class);
        $repository->delete(ProductionDelegateId::fromString(self::ID));
    }

    // --- Audit log ---------------------------------------------------

    public function test_an_event_is_inserted_with_ids_and_json_detail_only(): void
    {
        $db = new \wpdb(static fn () => FakeWpdbDefault::VALUE);
        $event = ParticipantEvent::record(
            ParticipantEvent::ACCEPTED,
            ProductionId::fromString(self::PRODUCTION),
            new DateTimeImmutable('2026-11-17T09:30:00+09:00'),
            ParticipantId::fromString(self::ID),
            ParticipantInvitationId::fromString(self::ID),
            null,
            ParticipantEvent::PATH_INVITATION,
            PersonId::fromString(self::PERSON),
            PersonId::fromString(self::PERSON),
            ['previous_status' => 'PENDING', 'reused_participant' => true]
        );

        (new WordPressParticipantEventRepository($db))->append($event);

        [$table, $row] = $db->callsTo('insert')[0];
        $this->assertSame('wp_stageart_participant_events', $table);
        $this->assertSame('ACCEPTED', $row['event_type']);
        $this->assertSame('INVITATION', $row['enrollment_path']);
        $this->assertSame('{"previous_status":"PENDING","reused_participant":true}', $row['detail']);
        $this->assertSame('2026-11-17 00:30:00', $row['created_at']);
        $this->assertNull($row['delegate_id']);
        $this->assertSame(
            ['id', 'production_id', 'participant_id', 'invitation_id', 'delegate_id', 'event_type', 'enrollment_path', 'actor_person_id', 'subject_person_id', 'detail', 'created_at'],
            array_keys($row)
        );
        $this->assertSame([], $db->callsTo('update'));
        $this->assertSame([], $db->callsTo('delete'));
    }

    public function test_an_empty_detail_is_null_and_a_failed_insert_throws(): void
    {
        $inserted = [];
        $fail = false;
        $db = new \wpdb(static function (string $method, array $args) use (&$inserted, &$fail) {
            if ($method === 'insert') {
                $inserted[] = $args[1];

                return $fail ? false : 1;
            }

            return FakeWpdbDefault::VALUE;
        });
        $repository = new WordPressParticipantEventRepository($db);
        $event = ParticipantEvent::record(ParticipantEvent::WITHDRAWN, ProductionId::fromString(self::PRODUCTION), new DateTimeImmutable());

        $repository->append($event);
        $this->assertNull($inserted[0]['detail']);

        $fail = true;
        $this->expectException(PersistenceException::class);
        $repository->append($event);
    }

    /** Unused by the R14-B flows but kept (stage 3 does not remove it). */
    public function test_find_by_token_hash_is_kept(): void
    {
        $this->assertTrue(method_exists(WordPressParticipantInvitationRepository::class, 'findByTokenHash'));
        $this->assertTrue(method_exists(Participant::class, 'reopenRequest'));
    }
}
