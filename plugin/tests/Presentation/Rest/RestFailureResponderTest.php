<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Production\ProductionClosedException;
use StageArt\Application\Shared\DatabaseBusyException;
use StageArt\Presentation\Rest\RestFailureResponder;

/**
 * PR #7 review (R5): what every stageart/v1 endpoint answers for an
 * exception its controller does not map itself - 409 for a closed
 * Production, 503 (retryable) for a lock wait timeout / deadlock that was
 * rolled back, 500 for anything else - and that every StageArt route
 * callback, and only those, is wrapped.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RestFailureResponderTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
    }

    public function test_a_capacity_below_the_reserved_seats_is_409_with_both_numbers(): void
    {
        $response = RestFailureResponder::toResponse(new \StageArt\Application\Performance\CapacityBelowReservationsException('perf-1', 40, 45));

        $this->assertSame(409, $response->get_status());
        $this->assertSame('stageart_capacity_below_reservations', $response->get_data()['code']);
        $this->assertSame(['status' => 409, 'capacity' => 40, 'reserved_seats' => 45], $response->get_data()['data']);
    }

    public function test_a_closed_production_is_409(): void
    {
        $response = RestFailureResponder::toResponse(new ProductionClosedException('p-1', 'CANCELLED'));

        $this->assertSame(409, $response->get_status());
        $this->assertSame('stageart_production_closed', $response->get_data()['code']);
        $this->assertSame(409, $response->get_data()['data']['status']);
        $this->assertStringContainsString('CANCELLED', $response->get_data()['message']);
        $this->assertArrayNotHasKey('Retry-After', $response->get_headers());
    }

    public function test_a_busy_database_is_503_retryable_with_retry_after(): void
    {
        $previous = new RuntimeException('Failed to lock the Production. Lock wait timeout exceeded; try restarting transaction');
        $response = RestFailureResponder::toResponse(new DatabaseBusyException('Lock wait timeout exceeded; try restarting transaction', $previous));

        $this->assertSame(503, $response->get_status());
        $this->assertSame('stageart_database_busy', $response->get_data()['code']);
        $this->assertSame(['status' => 503, 'retryable' => true], $response->get_data()['data']);
        $this->assertStringContainsString('nothing was saved', $response->get_data()['message']);
        $this->assertSame((string) RestFailureResponder::RETRY_AFTER_SECONDS, $response->get_headers()['Retry-After']);
        $this->assertStringNotContainsString('Failed to lock', $response->get_data()['message'], 'No database detail in the response');
    }

    public function test_any_other_failure_is_500_without_its_details(): void
    {
        $response = RestFailureResponder::toResponse(new RuntimeException("Failed to read the Reservations. Table 'stageart.wp_stageart_reservations' doesn't exist"));

        $this->assertSame(500, $response->get_status());
        $this->assertSame('stageart_internal_error', $response->get_data()['code']);
        $this->assertSame(['status' => 500], $response->get_data()['data'], 'not marked retryable');
        $this->assertStringNotContainsString('wp_stageart', $response->get_data()['message']);
        $this->assertArrayNotHasKey('Retry-After', $response->get_headers());
    }

    public function test_a_wrapped_callback_answers_as_before_when_it_does_not_throw(): void
    {
        $answer = new \WP_Error('stageart_capacity_exceeded', 'full', ['status' => 422]);
        $wrapped = RestFailureResponder::wrap(static fn (\WP_REST_Request $request) => $answer);

        $this->assertSame($answer, $wrapped(new \WP_REST_Request()));
    }

    public function test_a_wrapped_callback_maps_what_it_throws(): void
    {
        $request = new \WP_REST_Request(['id' => 'x']);
        $busy = RestFailureResponder::wrap(static function () {
            throw new DatabaseBusyException('Deadlock found when trying to get lock; try restarting transaction', new RuntimeException('x'));
        });
        $closed = RestFailureResponder::wrap(static function () {
            throw new ProductionClosedException('p-1', 'COMPLETED');
        });
        $broken = RestFailureResponder::wrap(static function () {
            throw new InvalidArgumentException('not mapped by this controller');
        });

        $this->assertSame(503, $busy($request)->get_status());
        $this->assertSame(409, $closed($request)->get_status());
        $this->assertSame(500, $broken($request)->get_status());
    }

    public function test_only_stageart_routes_are_wrapped(): void
    {
        $own = static fn () => 'own';
        $other = static fn () => 'other';
        $single = static fn () => 'single';

        $endpoints = RestFailureResponder::wrapEndpoints([
            '/stageart/v1/performances/(?P<id>[^/]+)/reservations' => [
                ['methods' => 'POST', 'callback' => $own],
                ['methods' => 'GET', 'callback' => $own],
                'schema' => 'not a handler',
            ],
            '/stageart/v1/single' => ['methods' => 'GET', 'callback' => $single],
            '/wp/v2/posts' => [['methods' => 'GET', 'callback' => $other]],
        ]);

        $route = $endpoints['/stageart/v1/performances/(?P<id>[^/]+)/reservations'];
        $this->assertNotSame($own, $route[0]['callback']);
        $this->assertNotSame($own, $route[1]['callback']);
        $this->assertSame('own', $route[0]['callback'](new \WP_REST_Request()));
        $this->assertSame('not a handler', $route['schema']);
        $this->assertSame('single', $endpoints['/stageart/v1/single']['callback'](new \WP_REST_Request()));
        $this->assertNotSame($single, $endpoints['/stageart/v1/single']['callback']);
        $this->assertSame($other, $endpoints['/wp/v2/posts'][0]['callback'], 'Other plugins\' routes are left alone');
    }
}
