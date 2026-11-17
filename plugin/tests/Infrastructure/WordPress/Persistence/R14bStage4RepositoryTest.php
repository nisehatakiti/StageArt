<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantRepository;
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
}
