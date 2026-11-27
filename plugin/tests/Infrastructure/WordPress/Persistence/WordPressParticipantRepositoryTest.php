<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Application\Shared\UniqueConstraintViolationException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantConsentSource;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantRepository;
use StageArt\Tests\Support\FakeMysqli;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 R14-B stage 2 (schema 1.49.0, design doc §4.5 / §6.1 / §7.4): the
 * R14-B columns are stored and read back as the Participant holds them,
 * NULL included, and a failed write is never ignored - a UNIQUE key
 * violation (1062) is told apart from any other failure.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressParticipantRepositoryTest extends TestCase
{
    private const ID = '11111111-1111-4111-8111-111111111111';
    private const PRODUCTION = '22222222-2222-4222-8222-222222222222';
    private const SUBJECT = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        require_once __DIR__ . '/../../../Support/fake-mysqli.php';
        FakeMysqli::$errno = 0;
    }

    private function repository(callable $responder): WordPressParticipantRepository
    {
        return new WordPressParticipantRepository(new \wpdb($responder));
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function participant(array $columns = []): Participant
    {
        $columns += [
            'status' => ParticipantStatus::ACTIVE,
            'enrollmentSource' => null,
            'consentSource' => null,
            'consentedAt' => null,
            'requestedAt' => null,
        ];

        return Participant::reconstitute(
            ParticipantId::fromString(self::ID),
            ProductionId::fromString(self::PRODUCTION),
            ParticipantSubjectType::person(),
            self::SUBJECT,
            ParticipantType::cast(),
            ParticipantStatus::fromString($columns['status']),
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
            new DateTimeImmutable('2026-11-02T11:00:00+00:00'),
            'remark',
            null,
            $columns['enrollmentSource'],
            $columns['consentSource'],
            $columns['consentedAt'],
            $columns['requestedAt']
        );
    }

    /**
     * @param array<string, string|null> $overrides
     * @return array<string, string|null>
     */
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'id' => self::ID,
            'production_id' => self::PRODUCTION,
            'subject_type' => 'PERSON',
            'subject_id' => self::SUBJECT,
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
     * Saves through a fake that reports the row as new (or existing) and
     * returns the write it received: [method, table, row].
     *
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function saved(Participant $participant, bool $existing = false): array
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

        $repository->save($participant);
        $this->assertNotNull($write, 'One write was issued');

        return $write;
    }

    private function hydrated(array $row): Participant
    {
        $repository = $this->repository(static fn (string $method) => $method === 'get_row' ? $row : FakeWpdbDefault::VALUE);
        $participant = $repository->findById(ParticipantId::fromString(self::ID));
        $this->assertNotNull($participant);

        return $participant;
    }

    public function test_a_new_participant_is_inserted_with_its_enrollment_route_consent_and_request_time(): void
    {
        [$method, $table, $row] = $this->saved($this->participant([
            'enrollmentSource' => ParticipantEnrollmentSource::invitation(),
            'consentSource' => ParticipantConsentSource::invitation(),
            'consentedAt' => new DateTimeImmutable('2026-11-03T12:34:56+00:00'),
            'requestedAt' => new DateTimeImmutable('2026-11-03T08:00:00+00:00'),
        ]));

        $this->assertSame('insert', $method);
        $this->assertSame('wp_stageart_participants', $table);
        $this->assertSame(self::ID, $row['id']);
        $this->assertSame('INVITATION', $row['enrollment_source']);
        $this->assertSame('INVITATION', $row['consent_source']);
        $this->assertSame('2026-11-03 12:34:56', $row['consented_at']);
        $this->assertSame('2026-11-03 08:00:00', $row['requested_at']);
        $this->assertSame('2026-11-01 10:00:00', $row['created_at']);
        $this->assertSame('2026-11-02 11:00:00', $row['updated_at']);
    }

    public function test_an_existing_participant_is_updated_with_the_same_columns_and_keeps_its_id(): void
    {
        [$method, $table, $row] = $this->saved($this->participant([
            'enrollmentSource' => ParticipantEnrollmentSource::joinRequest(),
            'consentSource' => ParticipantConsentSource::joinRequest(),
            'consentedAt' => new DateTimeImmutable('2026-11-04T01:02:03+00:00'),
            'requestedAt' => new DateTimeImmutable('2026-11-03T23:00:00+00:00'),
        ]), true);

        $this->assertSame('update', $method);
        $this->assertSame('wp_stageart_participants', $table);
        $this->assertArrayNotHasKey('id', $row);
        $this->assertArrayNotHasKey('created_at', $row);
        $this->assertSame('JOIN_REQUEST', $row['enrollment_source']);
        $this->assertSame('JOIN_REQUEST', $row['consent_source']);
        $this->assertSame('2026-11-04 01:02:03', $row['consented_at']);
        $this->assertSame('2026-11-03 23:00:00', $row['requested_at']);
    }

    /** NULL is stored as NULL: nothing is filled in (no route, no consent, no request time). */
    public function test_null_route_consent_and_request_time_are_stored_as_null(): void
    {
        foreach ([false, true] as $existing) {
            [, , $row] = $this->saved($this->participant(), $existing);

            $this->assertNull($row['enrollment_source']);
            $this->assertNull($row['consent_source']);
            $this->assertNull($row['consented_at']);
            $this->assertNull($row['requested_at']);
        }
    }

    /** requested_at is the request time it was given, never created_at. */
    public function test_requested_at_is_not_created_at(): void
    {
        [, , $row] = $this->saved($this->participant([
            'status' => ParticipantStatus::PENDING,
            'enrollmentSource' => ParticipantEnrollmentSource::joinRequest(),
            'requestedAt' => new DateTimeImmutable('2026-11-10T09:00:00+00:00'),
        ]));

        $this->assertSame('2026-11-10 09:00:00', $row['requested_at']);
        $this->assertSame('2026-11-01 10:00:00', $row['created_at']);
    }

    /** Date-times are stored in UTC: a +09:00 value is converted, not stored with its own digits. */
    public function test_date_times_are_stored_in_utc(): void
    {
        [, , $row] = $this->saved($this->participant([
            'consentSource' => ParticipantConsentSource::invitation(),
            'consentedAt' => new DateTimeImmutable('2026-11-03T09:00:00+09:00'),
            'requestedAt' => new DateTimeImmutable('2026-11-03T08:30:00+09:00'),
        ]));

        $this->assertSame('2026-11-03 00:00:00', $row['consented_at']);
        $this->assertSame('2026-11-02 23:30:00', $row['requested_at']);
    }

    public function test_a_row_with_the_r14b_columns_is_read_back(): void
    {
        $participant = $this->hydrated($this->row([
            'enrollment_source' => 'MANAGER_RECORD',
            'consent_source' => 'JOIN_REQUEST',
            'consented_at' => '2026-11-05 06:07:08',
            'requested_at' => '2026-11-05 01:00:00',
        ]));

        $this->assertSame(self::ID, $participant->id()->toString());
        $this->assertSame('MANAGER_RECORD', $participant->enrollmentSource()->toString());
        $this->assertSame('JOIN_REQUEST', $participant->consentSource()->toString());
        $this->assertEquals(new DateTimeImmutable('2026-11-05T06:07:08+00:00'), $participant->consentedAt());
        $this->assertEquals(new DateTimeImmutable('2026-11-05T01:00:00+00:00'), $participant->requestedAt());
        $this->assertTrue($participant->hasRecordedConsent());
    }

    /** A pre-R14-B row (the four columns NULL after the migration) reads back with no route and no consent. */
    public function test_a_pre_r14b_row_reads_back_with_nulls_and_without_consent(): void
    {
        $participant = $this->hydrated($this->row());

        $this->assertSame(self::ID, $participant->id()->toString());
        $this->assertNull($participant->enrollmentSource());
        $this->assertNull($participant->consentSource());
        $this->assertNull($participant->consentedAt());
        $this->assertNull($participant->requestedAt());
        $this->assertFalse($participant->hasRecordedConsent());
        $this->assertSame($participant->isActive(), $participant->isActivePerson(), 'Consent is no longer part of it (2026-11-27)');
        $this->assertEquals(new DateTimeImmutable('2026-11-01T10:00:00+00:00'), $participant->createdAt());
    }

    /** Stored then read back: the same values. */
    public function test_a_saved_row_reads_back_as_the_same_participant(): void
    {
        $original = $this->participant([
            'enrollmentSource' => ParticipantEnrollmentSource::invitation(),
            'consentSource' => ParticipantConsentSource::invitation(),
            'consentedAt' => new DateTimeImmutable('2026-11-03T12:00:00+00:00'),
            'requestedAt' => new DateTimeImmutable('2026-11-03T11:00:00+00:00'),
        ]);
        [, , $row] = $this->saved($original);

        $read = $this->hydrated($row);

        $this->assertSame('INVITATION', $read->enrollmentSource()->toString());
        $this->assertSame('INVITATION', $read->consentSource()->toString());
        $this->assertEquals($original->consentedAt(), $read->consentedAt());
        $this->assertEquals($original->requestedAt(), $read->requestedAt());
        $this->assertEquals($original->createdAt(), $read->createdAt());
        $this->assertEquals($original->updatedAt(), $read->updatedAt());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function writes(): array
    {
        return ['insert' => [false], 'update' => [true]];
    }

    /**
     * MySQL 1062 read from the connection's error number.
     *
     * @dataProvider writes
     */
    public function test_a_unique_key_violation_is_reported_as_such(bool $existing): void
    {
        $repository = $this->repository(function (string $method, array $args, FakeWpdb $db) use ($existing) {
            if ($method === 'get_var') {
                return $existing ? self::ID : null;
            }

            if ($method === 'insert' || $method === 'update') {
                $db->dbh = FakeMysqli::handle();
                FakeMysqli::$errno = 1062;
                $db->last_error = "Duplicate entry '" . self::PRODUCTION . "-PERSON-" . self::SUBJECT . "-CAST' for key 'production_subject_type'";

                return false;
            }

            return FakeWpdbDefault::VALUE;
        });

        try {
            $repository->save($this->participant());
            $this->fail('Expected UniqueConstraintViolationException');
        } catch (UniqueConstraintViolationException $exception) {
            $this->assertSame(1062, $exception->mysqlErrno());
            $this->assertStringNotContainsString('Duplicate entry', $exception->getMessage());
            $this->assertStringNotContainsString(self::SUBJECT, $exception->getMessage());
        }
    }

    /**
     * Without a mysqli handle, MySQL's "Duplicate entry" message decides.
     *
     * @dataProvider writes
     */
    public function test_a_duplicate_entry_message_is_a_unique_key_violation_without_a_connection_handle(bool $existing): void
    {
        $repository = $this->repository(function (string $method, array $args, FakeWpdb $db) use ($existing) {
            if ($method === 'get_var') {
                return $existing ? self::ID : null;
            }

            if ($method === 'insert' || $method === 'update') {
                $db->last_error = "Duplicate entry 'x' for key 'production_subject_type'";

                return false;
            }

            return FakeWpdbDefault::VALUE;
        });

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->participant());
    }

    /**
     * Any other failed write is a general persistence failure, not a 1062.
     *
     * @dataProvider writes
     */
    public function test_any_other_failed_write_is_a_persistence_failure(bool $existing): void
    {
        $repository = $this->repository(function (string $method, array $args, FakeWpdb $db) use ($existing) {
            if ($method === 'get_var') {
                return $existing ? self::ID : null;
            }

            if ($method === 'insert' || $method === 'update') {
                $db->dbh = FakeMysqli::handle();
                FakeMysqli::$errno = 1146;
                $db->last_error = "Table 'wp_stageart_participants' doesn't exist";

                return false;
            }

            return FakeWpdbDefault::VALUE;
        });

        try {
            $repository->save($this->participant());
            $this->fail('Expected PersistenceException');
        } catch (PersistenceException $exception) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $exception);
            $this->assertSame(1146, $exception->mysqlErrno());
            $this->assertStringNotContainsString("doesn't exist", $exception->getMessage());
        }
    }

    /** update() returning 0 (no row changed, e.g. the same values again) is not a failure. */
    public function test_an_update_that_changes_no_row_is_not_a_failure(): void
    {
        $updates = 0;
        $repository = $this->repository(function (string $method) use (&$updates) {
            if ($method === 'get_var') {
                return self::ID;
            }

            if ($method === 'update') {
                $updates++;

                return 0;
            }

            return FakeWpdbDefault::VALUE;
        });

        $repository->save($this->participant());

        $this->assertSame(1, $updates);
    }

    /** A failed existence read throws; it is never taken as "new row" and turned into an INSERT. */
    public function test_a_failed_existence_read_throws_and_writes_nothing(): void
    {
        $writes = 0;
        $repository = $this->repository(function (string $method, array $args, FakeWpdb $db) use (&$writes) {
            if ($method === 'get_var') {
                $db->last_error = 'Lost connection to MySQL server during query';

                return null;
            }

            if ($method === 'insert' || $method === 'update') {
                $writes++;
            }

            return FakeWpdbDefault::VALUE;
        });

        try {
            $repository->save($this->participant());
            $this->fail('Expected PersistenceException');
        } catch (PersistenceException $exception) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $exception);
            $this->assertSame(0, $writes);
        }
    }
}
