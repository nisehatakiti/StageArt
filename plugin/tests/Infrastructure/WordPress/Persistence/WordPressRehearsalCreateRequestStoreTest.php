<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Rehearsal\RehearsalCreateRequestDuplicateException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressRehearsalCreateRequestStore;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 R12: only a duplicate-key error means "another request recorded
 * this key first"; any other write or read failure is an error.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressRehearsalCreateRequestStoreTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
    }

    private function store(callable $responder): WordPressRehearsalCreateRequestStore
    {
        return new WordPressRehearsalCreateRequestStore(new \wpdb($responder));
    }

    public function test_record_inserts_the_key_hash_and_rehearsal(): void
    {
        $inserted = null;
        $store = $this->store(function (string $method, array $args) use (&$inserted) {
            if ($method === 'insert') {
                $inserted = $args;
            }

            return FakeWpdbDefault::VALUE;
        });
        $productionId = ProductionId::generate();
        $personId = PersonId::generate();
        $rehearsalId = RehearsalId::generate();

        $store->record($productionId, 'key-1', $personId, str_repeat('a', 64), $rehearsalId);

        $this->assertSame('wp_stageart_rehearsal_create_requests', $inserted[0]);
        $this->assertSame($productionId->toString(), $inserted[1]['production_id']);
        $this->assertSame('key-1', $inserted[1]['idempotency_key']);
        $this->assertSame($personId->toString(), $inserted[1]['requested_by']);
        $this->assertSame(str_repeat('a', 64), $inserted[1]['request_hash']);
        $this->assertSame($rehearsalId->toString(), $inserted[1]['rehearsal_id']);
    }

    public function test_a_duplicate_key_error_is_reported_as_a_duplicate(): void
    {
        $store = $this->store(function (string $method, array $args, FakeWpdb $db) {
            if ($method === 'insert') {
                $db->last_error = "Duplicate entry 'x-key-1' for key 'production_idempotency_key'";

                return false;
            }

            return FakeWpdbDefault::VALUE;
        });

        $this->expectException(RehearsalCreateRequestDuplicateException::class);

        $store->record(ProductionId::generate(), 'key-1', PersonId::generate(), str_repeat('a', 64), RehearsalId::generate());
    }

    public function test_any_other_insert_error_is_an_error_not_a_duplicate(): void
    {
        $store = $this->store(function (string $method, array $args, FakeWpdb $db) {
            if ($method === 'insert') {
                $db->last_error = "Table 'wp_stageart_rehearsal_create_requests' doesn't exist";

                return false;
            }

            return FakeWpdbDefault::VALUE;
        });

        try {
            $store->record(ProductionId::generate(), 'key-1', PersonId::generate(), str_repeat('a', 64), RehearsalId::generate());
            $this->fail('An insert error was ignored.');
        } catch (RehearsalCreateRequestDuplicateException $wrong) {
            $this->fail('A non-duplicate error was reported as a duplicate.');
        } catch (RuntimeException $expected) {
            $this->assertStringContainsString("doesn't exist", $expected->getMessage());
        }
    }

    public function test_find_returns_the_record_or_null(): void
    {
        $productionId = ProductionId::generate();
        $personId = PersonId::generate();
        $rehearsalId = RehearsalId::generate();
        $store = $this->store(function (string $method, array $args, FakeWpdb $db) use ($productionId, $personId, $rehearsalId) {
            if ($method === 'get_row') {
                $prepared = $db->preparedFrom($args[0]);

                return $prepared['args'] === [$productionId->toString(), 'key-1']
                    ? ['requested_by' => $personId->toString(), 'request_hash' => str_repeat('b', 64), 'rehearsal_id' => $rehearsalId->toString()]
                    : null;
            }

            return FakeWpdbDefault::VALUE;
        });

        $record = $store->find($productionId, 'key-1');

        $this->assertNotNull($record);
        $this->assertSame($personId->toString(), $record->requestedBy->toString());
        $this->assertSame(str_repeat('b', 64), $record->requestHash);
        $this->assertSame($rehearsalId->toString(), $record->rehearsalId->toString());
        $this->assertNull($store->find($productionId, 'other-key'));
        $this->assertNull($store->find(ProductionId::generate(), 'key-1'));
    }

    public function test_a_read_error_is_not_mistaken_for_an_unknown_key(): void
    {
        $store = $this->store(function (string $method, array $args, FakeWpdb $db) {
            if ($method === 'get_row') {
                $db->last_error = 'Lost connection to MySQL server during query';

                return null;
            }

            return FakeWpdbDefault::VALUE;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lost connection');

        $store->find(ProductionId::generate(), 'key-1');
    }
}
