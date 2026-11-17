<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantInvitationRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantInvitationSendRepository;
use StageArt\Infrastructure\WordPress\ParticipantInvitation\WordPressConfigEmailKeyring;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\Person\PersonId;
use StageArt\Tests\Support\FakeMysqli;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 R14-B stage 4 (design doc §4.6 / §7.4): the Repository reads the
 * delegate-effectiveness decision relies on. Each one clears last_error,
 * and a failed read throws (PersistenceException, errno only) instead of
 * answering "no" / "none".
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class R14bStage4RepositoryTest extends TestCase
{
    private const ID = '11111111-1111-4111-8111-111111111111';
    private const PRODUCTION = '22222222-2222-4222-8222-222222222222';
    private const PERSON = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        require_once __DIR__ . '/../../../Support/fake-mysqli.php';
        FakeMysqli::$errno = 0;
    }

    private static function failRead(\wpdb $db): void
    {
        $db->dbh = FakeMysqli::handle();
        FakeMysqli::$errno = 1205;
        $db->last_error = 'Lock wait timeout exceeded; try restarting transaction';
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
            'participant_type' => 'STAFF',
            'status' => 'ACTIVE',
            'remarks' => null,
            'display_name' => null,
            'created_at' => '2026-11-01 10:00:00',
            'updated_at' => '2026-11-02 11:00:00',
            'enrollment_source' => 'INVITATION',
            'consent_source' => 'INVITATION',
            'consented_at' => '2026-11-02 11:00:00',
            'requested_at' => null,
        ];
    }

    public function test_consented_active_person_check_is_one_indexed_read_without_a_lock(): void
    {
        $db = new \wpdb(static fn (string $method) => $method === 'get_var' ? self::ID : FakeWpdbDefault::VALUE);
        $repository = new WordPressParticipantRepository($db);

        $this->assertTrue($repository->existsConsentedActivePersonParticipant(ProductionId::fromString(self::PRODUCTION), self::PERSON));

        $sql = $db->callsTo('get_var')[0][0];
        $this->assertStringContainsString("production_id = '" . self::PRODUCTION . "'", $sql);
        $this->assertStringContainsString("subject_type = 'PERSON'", $sql, 'NAME_ONLY / ORGANIZATION never count');
        $this->assertStringContainsString("subject_id = '" . self::PERSON . "'", $sql);
        $this->assertStringContainsString("status = 'ACTIVE'", $sql);
        $this->assertStringContainsString('consented_at IS NOT NULL', $sql, 'created_at is never taken for consent');
        $this->assertStringNotContainsString('FOR UPDATE', $sql);
        $this->assertStringNotContainsString('participant_type', $sql, 'Either type counts');
    }

    public function test_consented_active_person_check_is_false_for_no_row(): void
    {
        $repository = new WordPressParticipantRepository(new \wpdb(static fn () => FakeWpdbDefault::VALUE));

        $this->assertFalse($repository->existsConsentedActivePersonParticipant(ProductionId::fromString(self::PRODUCTION), self::PERSON));
    }

    public function test_a_failed_consent_check_throws_instead_of_answering_no(): void
    {
        $repository = new WordPressParticipantRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            if ($method === 'get_var') {
                self::failRead($db);

                return null;
            }

            return FakeWpdbDefault::VALUE;
        }));

        try {
            $repository->existsConsentedActivePersonParticipant(ProductionId::fromString(self::PRODUCTION), self::PERSON);
            $this->fail('A failed read must throw.');
        } catch (PersistenceException $e) {
            $this->assertSame(1205, $e->mysqlErrno());
            $this->assertStringNotContainsString('Lock wait', $e->getMessage());
        }
    }

    public function test_a_stale_error_does_not_fail_the_consent_check(): void
    {
        $db = new \wpdb(static fn () => FakeWpdbDefault::VALUE);
        $db->last_error = 'an earlier, unrelated failure';

        $this->assertFalse((new WordPressParticipantRepository($db))->existsConsentedActivePersonParticipant(ProductionId::fromString(self::PRODUCTION), self::PERSON));
    }

    public function test_person_rows_are_read_plainly_or_locked_and_hydrated(): void
    {
        $db = new \wpdb(fn (string $method) => $method === 'get_results' ? [$this->participantRow()] : FakeWpdbDefault::VALUE);
        $repository = new WordPressParticipantRepository($db);

        $plain = $repository->findPersonParticipants(ProductionId::fromString(self::PRODUCTION), self::PERSON);
        $locked = $repository->findPersonParticipantsForUpdate(ProductionId::fromString(self::PRODUCTION), self::PERSON);

        $this->assertTrue($plain[0]->isConsentedActivePerson());
        $this->assertSame(self::ID, $locked[0]->id()->toString());

        [$plainSql, $lockedSql] = [$db->callsTo('get_results')[0][0], $db->callsTo('get_results')[1][0]];
        $this->assertStringContainsString("subject_type = 'PERSON'", $plainSql);
        $this->assertStringNotContainsString('FOR UPDATE', $plainSql);
        $this->assertStringEndsWith('ORDER BY id FOR UPDATE', $lockedSql);
    }

    public function test_a_failed_person_row_read_throws(): void
    {
        $repository = new WordPressParticipantRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            if ($method === 'get_results') {
                self::failRead($db);

                return [];
            }

            return FakeWpdbDefault::VALUE;
        }));

        $this->expectException(PersistenceException::class);
        $repository->findPersonParticipantsForUpdate(ProductionId::fromString(self::PRODUCTION), self::PERSON);
    }

    // --- invitation sends (§6.2 / §6.7 / §11.3) ------------------------

    private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function send(): ParticipantInvitationSend
    {
        return ParticipantInvitationSend::accept(
            ParticipantInvitationId::fromString(self::ID),
            ProductionId::fromString(self::PRODUCTION),
            self::KEY,
            'v1',
            ParticipantInvitationSend::KIND_INITIAL,
            PersonId::fromString(self::PERSON),
            new \DateTimeImmutable('2026-11-20T09:00:00+09:00')
        );
    }

    public function test_a_send_is_inserted_pending_with_its_key_but_no_address(): void
    {
        $db = new \wpdb(static fn () => FakeWpdbDefault::VALUE);
        (new WordPressParticipantInvitationSendRepository($db))->add($this->send());

        [$table, $row] = $db->callsTo('insert')[0];
        $this->assertSame('wp_stageart_participant_invitation_sends', $table);
        $this->assertSame('PENDING', $row['status']);
        $this->assertSame(self::KEY, $row['email_key']);
        $this->assertSame('v1', $row['email_key_version']);
        $this->assertSame('2026-11-20 00:00:00', $row['requested_at'], 'UTC');
        $this->assertNull($row['completed_at']);
        $this->assertSame(['id', 'invitation_id', 'production_id', 'email_key', 'email_key_version', 'kind', 'status', 'requested_by_person_id', 'requested_at', 'completed_at'], array_keys($row));
    }

    public function test_a_failed_send_insert_throws(): void
    {
        $repository = new WordPressParticipantInvitationSendRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            if ($method === 'insert') {
                self::failRead($db);

                return false;
            }

            return FakeWpdbDefault::VALUE;
        }));

        $this->expectException(PersistenceException::class);
        $repository->add($this->send());
    }

    public function test_the_window_reads_use_the_production_period_and_version_key_pairs(): void
    {
        $db = new \wpdb(static fn (string $method) => $method === 'get_col' ? ['2026-11-01 00:00:00', '2026-11-05 00:00:00'] : FakeWpdbDefault::VALUE);
        $repository = new WordPressParticipantInvitationSendRepository($db);
        $since = new \DateTimeImmutable('2026-10-21T00:00:00+00:00');

        $repository->keyVersionsSince(ProductionId::fromString(self::PRODUCTION), $since);
        $times = $repository->requestedAtsForKeysSince(ProductionId::fromString(self::PRODUCTION), $since, ['v1' => self::KEY, 'v2' => str_repeat('b', 64)]);

        [$versionsSql, $countSql] = [$db->callsTo('get_col')[0][0], $db->callsTo('get_col')[1][0]];
        $this->assertStringContainsString("SELECT DISTINCT email_key_version", $versionsSql);
        $this->assertStringContainsString("requested_at >= '2026-10-21 00:00:00'", $versionsSql);
        $this->assertStringContainsString("(email_key_version = 'v1' AND email_key = '" . self::KEY . "')", $countSql);
        $this->assertStringContainsString(" OR (email_key_version = 'v2'", $countSql);
        $this->assertStringContainsString('ORDER BY requested_at ASC', $countSql);
        $this->assertSame('2026-11-01T00:00:00+00:00', $times[0]->format(DATE_ATOM));
    }

    public function test_a_failed_window_read_throws_instead_of_counting_zero(): void
    {
        $repository = new WordPressParticipantInvitationSendRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            if ($method === 'get_col') {
                self::failRead($db);

                return [];
            }

            return FakeWpdbDefault::VALUE;
        }));

        $this->expectException(PersistenceException::class);
        $repository->requestedAtsForKeysSince(ProductionId::fromString(self::PRODUCTION), new \DateTimeImmutable(), ['v1' => self::KEY]);
    }

    public function test_a_result_is_recorded_only_on_a_pending_row(): void
    {
        $affected = 1;
        $db = new \wpdb(static function (string $method) use (&$affected) {
            return $method === 'query' ? $affected : FakeWpdbDefault::VALUE;
        });
        $repository = new WordPressParticipantInvitationSendRepository($db);

        $this->assertTrue($repository->completeIfPending(self::ID, 'SENT', new \DateTimeImmutable('2026-11-20T00:00:00+00:00')));
        $affected = 0;
        $this->assertFalse($repository->completeIfPending(self::ID, 'FAILED', new \DateTimeImmutable()));

        $sql = $db->callsTo('query')[0][0];
        $this->assertStringContainsString("SET status = 'SENT', completed_at = '2026-11-20 00:00:00' WHERE id = '" . self::ID . "' AND status = 'PENDING'", $sql);

        $this->expectException(\InvalidArgumentException::class);
        $repository->completeIfPending(self::ID, 'PENDING', new \DateTimeImmutable());
    }

    // --- invitation reads (stage 4) --------------------------------------

    private function invitationRow(array $overrides = []): array
    {
        return $overrides + [
            'id' => self::ID, 'production_id' => self::PRODUCTION, 'email' => 'Taro@Example.com', 'invited_name' => 'Taro',
            'invited_by_person_id' => self::PERSON, 'participant_type' => 'CAST', 'remarks' => null, 'token_hash' => str_repeat('a', 64),
            'status' => 'PENDING', 'created_at' => '2026-11-10 00:00:00', 'expires_at' => '2026-11-17 00:00:00', 'consumed_at' => null,
            'invitee_person_id' => null, 'responded_at' => null, 'participant_id' => null, 'close_reason' => null, 'closed_at' => null,
            'closed_by_person_id' => null, 'resend_count' => '0', 'revocation_notified_at' => null,
            'current_send_id' => '99999999-9999-4999-8999-999999999999', 'last_sent_at' => '2026-11-10 00:00:00', 'updated_at' => null,
        ];
    }

    public function test_same_address_invitations_are_locked_and_matched_exactly_after_normalisation(): void
    {
        $db = new \wpdb(fn (string $method) => $method === 'get_results'
            ? [$this->invitationRow(), $this->invitationRow(['id' => '55555555-5555-4555-8555-555555555555', 'email' => 'taro@example.co'])]
            : FakeWpdbDefault::VALUE);
        $repository = new WordPressParticipantInvitationRepository($db);

        $found = $repository->findByProductionEmailAndTypeForUpdate(ProductionId::fromString(self::PRODUCTION), ' taro@example.com ', ParticipantType::cast());

        $this->assertCount(1, $found);
        $this->assertSame('99999999-9999-4999-8999-999999999999', $found[0]->currentSendId());
        $this->assertStringEndsWith('ORDER BY id FOR UPDATE', $db->callsTo('get_results')[0][0]);
        $this->assertStringContainsString("email = 'taro@example.com'", $db->callsTo('get_results')[0][0], 'trimmed');
    }

    public function test_the_latest_rejection_is_found_by_normalised_address_and_failures_throw(): void
    {
        $db = new \wpdb(static fn (string $method) => $method === 'get_results'
            ? [['email' => 'TARO@example.com', 'responded_at' => '2026-11-01 00:00:00'], ['email' => 'taro@example.com', 'responded_at' => '2026-11-05 00:00:00'], ['email' => 'other@example.com', 'responded_at' => '2026-11-09 00:00:00']]
            : FakeWpdbDefault::VALUE);
        $repository = new WordPressParticipantInvitationRepository($db);

        $latest = $repository->latestRejectionAt(ProductionId::fromString(self::PRODUCTION), 'taro@example.com');

        $this->assertSame('2026-11-05T00:00:00+00:00', $latest->format(DATE_ATOM));
        $this->assertStringContainsString("status = 'REJECTED' AND responded_at IS NOT NULL", $db->callsTo('get_results')[0][0]);
        $this->assertStringNotContainsString('participant_type', $db->callsTo('get_results')[0][0], 'Any type');

        $failing = new WordPressParticipantInvitationRepository(new \wpdb(static function (string $method, array $args, \wpdb $db) {
            self::failRead($db);

            return $method === 'get_var' ? null : [];
        }));

        foreach ([fn () => $failing->latestRejectionAt(ProductionId::fromString(self::PRODUCTION), 'x@example.com'), fn () => $failing->currentSendIdOf(ParticipantInvitationId::fromString(self::ID))] as $read) {
            try {
                $read();
                $this->fail('A failed read must throw.');
            } catch (PersistenceException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_current_send_id_is_read_without_a_lock(): void
    {
        $db = new \wpdb(static fn (string $method) => $method === 'get_var' ? '99999999-9999-4999-8999-999999999999' : FakeWpdbDefault::VALUE);

        $this->assertSame('99999999-9999-4999-8999-999999999999', (new WordPressParticipantInvitationRepository($db))->currentSendIdOf(ParticipantInvitationId::fromString(self::ID)));
        $this->assertStringNotContainsString('FOR UPDATE', $db->callsTo('get_var')[0][0]);
    }

    // --- keyring (§6.7) ---------------------------------------------------

    public function test_the_keyring_accepts_only_usable_versions_and_keys(): void
    {
        $ring = new WordPressConfigEmailKeyring([
            'v1' => str_repeat('a', 32),
            'short' => 'too-short',
            'bad version!' => str_repeat('b', 32),
        ], 'v1', false);

        $this->assertSame('v1', $ring->currentVersion());
        $this->assertSame(str_repeat('a', 32), $ring->keyFor('v1'));
        $this->assertNull($ring->keyFor('short'), 'A key under 32 bytes is missing');
        $this->assertNull($ring->keyFor('bad version!'));
        $this->assertNull((new WordPressConfigEmailKeyring(['v1' => str_repeat('a', 32)], 'v2', false))->currentVersion(), 'Current version without its key');
        $this->assertNull((new WordPressConfigEmailKeyring([], null, false))->currentVersion());
    }

    public function test_without_the_constants_nothing_is_configured(): void
    {
        $ring = new WordPressConfigEmailKeyring();

        $this->assertFalse(defined('STAGEART_EMAIL_KEYS'));
        $this->assertNull($ring->currentVersion());
    }
}
