<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\RateLimit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\RateLimit\RateLimitedException;
use StageArt\Application\RateLimit\RateLimitRule;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Infrastructure\WordPress\RateLimit\WordPressRateLimiter;
use StageArt\Tests\Support\FakeMysqli;

/**
 * PR #7 R14-B stage 8 (design doc §9.2, §17.5.3): the statements the
 * rate limiter sends, the deadlock retry and the failures. (The atomic
 * count under parallel processes is checked on MySQL 5.7 in the isolated
 * environment.)
 */
final class WordPressRateLimiterTest extends TestCase
{
    private const PERSON = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        require_once __DIR__ . '/../../../Support/fake-mysqli.php';
        FakeMysqli::$errno = 0;
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-18T19:20:30+09:00');
    }

    public function test_a_hit_is_one_upsert_then_last_insert_id(): void
    {
        $db = new \wpdb(static fn (string $method, array $args) => $method === 'get_var' ? '7' : 1);
        $limiter = new WordPressRateLimiter($db, 'secret');

        $this->assertSame(7, $limiter->hit(RateLimitRule::invitationSendPerPerson(), self::PERSON, self::now()));

        $insert = $db->preparedFrom($db->callsTo('query')[0][0]);
        $this->assertSame(
            'INSERT INTO wp_stageart_rate_limit_buckets (bucket_key, window_start, hits, expires_at) VALUES (%s, %s, LAST_INSERT_ID(1), %s) ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)',
            $insert['template']
        );
        $this->assertSame(
            [hash_hmac('sha256', 'invitation_create|person|' . self::PERSON, 'secret'), '2026-11-18 10:00:00', '2026-11-18 11:00:00'],
            $insert['args'],
            'UTC window; the bucket key is the HMAC'
        );
        $this->assertSame('SELECT LAST_INSERT_ID()', $db->callsTo('get_var')[0][0]);
        $this->assertCount(1, $db->callsTo('query'));
    }

    public function test_the_bucket_key_hides_the_subject(): void
    {
        $limiter = new WordPressRateLimiter(new \wpdb(), 'secret');
        $key = $limiter->bucketKey(RateLimitRule::invitationSendPerPerson(), self::PERSON);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
        $this->assertStringNotContainsString(self::PERSON, $key);
        $this->assertNotSame($key, (new WordPressRateLimiter(new \wpdb(), 'other'))->bucketKey(RateLimitRule::invitationSendPerPerson(), self::PERSON), 'Keyed by the secret');
        $this->assertNotSame($key, $limiter->bucketKey(RateLimitRule::invitationRespondFailurePerPerson(), self::PERSON), 'Each kind has its own bucket');
        $this->assertNotSame(
            $limiter->bucketKey(RateLimitRule::invitationSendPerPerson(), 'X'),
            $limiter->bucketKey(RateLimitRule::invitationSendPerProduction(), 'X'),
            'A Person and a Production with the same id never share a bucket'
        );
    }

    /** Two-step login: the account check counts an address and an IP - neither is stored. */
    public function test_account_check_bucket_keys_hide_the_address_and_the_ip(): void
    {
        $limiter = new WordPressRateLimiter(new \wpdb(), 'secret');
        $emailKey = $limiter->bucketKey(RateLimitRule::accountCheckPerEmail(), 'private@example.com');
        $clientKey = $limiter->bucketKey(RateLimitRule::accountCheckPerClient(), '192.0.2.44');

        foreach ([$emailKey, $clientKey] as $key) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
            $this->assertStringNotContainsString('private', $key);
            $this->assertStringNotContainsString('192.0.2.44', $key);
        }
        $this->assertSame(hash_hmac('sha256', 'account_check|email|private@example.com', 'secret'), $emailKey);
        $this->assertNotSame(
            $limiter->bucketKey(RateLimitRule::accountCheckPerClient(), 'X'),
            $limiter->bucketKey(RateLimitRule::accountCheckPerEmail(), 'X'),
            'A connection and an address never share a bucket'
        );
        $this->assertNotSame(
            $limiter->bucketKey(RateLimitRule::accountCheckPerClient(), 'X'),
            $limiter->bucketKey(RateLimitRule::invitationSendPerPerson(), 'X'),
            'Never shares a bucket with an invitation limit'
        );
    }

    public function test_an_account_check_hit_uses_its_own_ten_minute_window(): void
    {
        $db = new \wpdb(static fn (string $method, array $args) => $method === 'get_var' ? '3' : 1);
        $limiter = new WordPressRateLimiter($db, 'secret');

        $this->assertSame(3, $limiter->hit(RateLimitRule::accountCheckPerEmail(), 'private@example.com', self::now()));

        $insert = $db->preparedFrom($db->callsTo('query')[0][0]);
        $this->assertSame(
            [hash_hmac('sha256', 'account_check|email|private@example.com', 'secret'), '2026-11-18 10:20:00', '2026-11-18 10:30:00'],
            $insert['args']
        );
    }

    public function test_a_deadlock_is_retried_and_the_count_returned(): void
    {
        $attempts = 0;
        $db = new \wpdb(static function (string $method) use (&$attempts) {
            if ($method === 'query') {
                $attempts++;
                FakeMysqli::$errno = $attempts < 3 ? 1213 : 0;

                return $attempts < 3 ? false : 1;
            }

            return $method === 'get_var' ? '3' : 1;
        });
        $db->dbh = FakeMysqli::handle();
        $slept = [];
        $limiter = new WordPressRateLimiter($db, 'secret', static function (int $us) use (&$slept): void {
            $slept[] = $us;
        });

        $this->assertSame(3, $limiter->hit(RateLimitRule::invitationSendPerPerson(), self::PERSON, self::now()));
        $this->assertSame(3, $attempts);
        $this->assertCount(2, $slept);

        foreach ($slept as $us) {
            $this->assertGreaterThanOrEqual(10000, $us);
            $this->assertLessThanOrEqual(50000, $us);
        }
    }

    public function test_three_deadlocks_are_429_with_a_short_retry_after(): void
    {
        $db = new \wpdb(static fn (string $method) => $method === 'query' ? false : 1);
        $db->dbh = FakeMysqli::handle();
        FakeMysqli::$errno = 1213;
        $limiter = new WordPressRateLimiter($db, 'secret', static function (): void {
        });

        try {
            $limiter->hit(RateLimitRule::invitationSendPerPerson(), self::PERSON, self::now());
            $this->fail('The safe side is 429');
        } catch (RateLimitedException $exception) {
            $this->assertSame(RateLimitedException::UNAVAILABLE_RETRY_AFTER, $exception->retryAfter());
        }

        $this->assertCount(3, $db->callsTo('query'), 'Three tries in all');
    }

    public function test_another_failure_is_a_persistence_failure_without_retry_or_database_text(): void
    {
        $db = new \wpdb(static fn (string $method) => $method === 'query' ? false : 1);
        $db->dbh = FakeMysqli::handle();
        $db->last_error = "Table 'wp_stageart_rate_limit_buckets' doesn't exist";
        FakeMysqli::$errno = 1146;

        try {
            (new WordPressRateLimiter($db, 'secret'))->hit(RateLimitRule::invitationSendPerPerson(), self::PERSON, self::now());
            $this->fail('500 expected');
        } catch (PersistenceException $exception) {
            $this->assertSame(1146, $exception->mysqlErrno());
            $this->assertStringNotContainsString("doesn't exist", $exception->getMessage());
        }

        $this->assertCount(1, $db->callsTo('query'));
    }

    public function test_count_reads_the_current_window_without_writing(): void
    {
        $db = new \wpdb(static fn (string $method) => $method === 'get_var' ? '4' : 1);
        $limiter = new WordPressRateLimiter($db, 'secret');

        $this->assertSame(4, $limiter->count(RateLimitRule::invitationRespondFailurePerPerson(), self::PERSON, self::now()));

        $read = $db->preparedFrom($db->callsTo('get_var')[0][0]);
        $this->assertSame('SELECT hits FROM wp_stageart_rate_limit_buckets WHERE bucket_key = %s AND window_start = %s', $read['template']);
        $this->assertSame('2026-11-18 10:20:00', $read['args'][1]);
        $this->assertSame([], $db->callsTo('query'));
    }

    public function test_count_is_zero_without_a_row_and_a_failed_read_throws(): void
    {
        $this->assertSame(0, (new WordPressRateLimiter(new \wpdb(static fn () => null), 'secret'))->count(RateLimitRule::invitationRespondFailurePerPerson(), self::PERSON, self::now()));

        $db = new \wpdb(static function (string $method, array $args, $wpdb) {
            $wpdb->last_error = 'Lock wait timeout exceeded';

            return null;
        });

        $this->expectException(PersistenceException::class);
        (new WordPressRateLimiter($db, 'secret'))->count(RateLimitRule::invitationRespondFailurePerPerson(), self::PERSON, self::now());
    }
}
