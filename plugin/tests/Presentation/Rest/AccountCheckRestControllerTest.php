<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Authentication\AccountCheckRateLimit;
use StageArt\Application\Authentication\CheckAccountUseCase;
use StageArt\Application\RateLimit\RateLimitRule;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Presentation\Rest\AccountCheckRestController;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryExternalIdentityRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryRateLimiter;
use StageArt\Tests\Support\InMemoryUserAccountRepository;
use WP_REST_Request;

/**
 * POST /auth/account/check on the R14-B rate limiter: the answers (200 /
 * 422 / 429 / 500) all carry Cache-Control: no-store; the 429 is R14-B's
 * `stageart_rate_limited` with retry_after and Retry-After; only
 * REMOTE_ADDR counts as the connection (X-Forwarded-For is ignored); and
 * nothing about the request (address, IP) reaches the log.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AccountCheckRestControllerTest extends TestCase
{
    private const CLIENT = '192.0.2.10';

    private InMemoryRateLimiter $limiter;
    private DateTimeImmutable $now;
    private string $log;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'stageart-account-check-log');
        ini_set('error_log', $this->log);
        $_SERVER['REMOTE_ADDR'] = self::CLIENT;
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->limiter = new InMemoryRateLimiter();
        // 06:03:20 UTC -> the 10-minute window ends at 06:10:00 (400 s).
        $this->now = new DateTimeImmutable('2026-11-25T06:03:20', new DateTimeZone('UTC'));
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
    }

    public function test_an_answer_is_never_cached(): void
    {
        $response = $this->controller()->check(new WP_REST_Request(['email' => 'nobody@example.com']));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['status' => 'NOT_FOUND', 'methods' => []], $response->get_data());
        $this->assertSame('no-store', $response->get_headers()['Cache-Control']);
    }

    public function test_an_invalid_address_is_422_and_never_cached(): void
    {
        $response = $this->controller()->check(new WP_REST_Request(['email' => 'not-an-address']));

        $this->assertSame(422, $response->get_status());
        $this->assertSame('stageart_account_check_invalid_email', $response->get_data()['code']);
        $this->assertSame('no-store', $response->get_headers()['Cache-Control']);
    }

    public function test_over_the_limit_is_r14b_429_with_retry_after_and_never_cached(): void
    {
        $limit = RateLimitRule::accountCheckPerClient()->limit();

        for ($i = 1; $i <= $limit; $i++) {
            $this->assertSame(200, $this->controller()->check(new WP_REST_Request(['email' => "user{$i}@example.com"]))->get_status());
        }

        $response = $this->controller()->check(new WP_REST_Request(['email' => 'one-more@example.com']));

        $this->assertSame(429, $response->get_status());
        $this->assertSame('stageart_rate_limited', $response->get_data()['code']);
        $this->assertSame(['status' => 429, 'retry_after' => 400], $response->get_data()['data']);
        $this->assertSame('400', $response->get_headers()['Retry-After']);
        $this->assertSame('no-store', $response->get_headers()['Cache-Control']);
        $this->assertSame('', (string) file_get_contents($this->log), 'a refused check is not logged');
    }

    public function test_only_remote_addr_counts_as_the_connection(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.77';

        $this->controller()->check(new WP_REST_Request(['email' => 'nobody@example.com']));

        $this->assertSame(1, $this->limiter->countFor(RateLimitRule::accountCheckPerClient(), self::CLIENT, $this->now));
        $this->assertSame(0, $this->limiter->countFor(RateLimitRule::accountCheckPerClient(), '203.0.113.77', $this->now));
    }

    public function test_a_counter_failure_is_500_never_cached_and_logs_neither_address_nor_ip(): void
    {
        $this->limiter->failNextHit = new PersistenceException('count in', 'wp_stageart_rate_limit_buckets', 1146);

        $response = $this->controller()->check(new WP_REST_Request(['email' => 'private@example.com']));

        $this->assertSame(500, $response->get_status());
        $this->assertSame('stageart_persistence_failed', $response->get_data()['code']);
        $this->assertSame('no-store', $response->get_headers()['Cache-Control']);
        $log = (string) file_get_contents($this->log);
        $this->assertStringNotContainsString('private@example.com', $log);
        $this->assertStringNotContainsString(self::CLIENT, $log);
    }

    private function controller(): AccountCheckRestController
    {
        return new AccountCheckRestController(new CheckAccountUseCase(
            new InMemoryEmailCredentialRepository(),
            new InMemoryUserAccountRepository(),
            new InMemoryExternalIdentityRepository(),
            new InMemoryNotificationEmailRepository(),
            new AccountCheckRateLimit($this->limiter, fn (): DateTimeImmutable => $this->now)
        ));
    }
}
