<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\RateLimit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\ParticipantInvitation\AcceptParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\InvitationRecipientNotEligibleException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationAccessDeniedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotPendingException;
use StageArt\Application\ParticipantInvitation\RejectParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\RateLimit\InvitationRateLimit;
use StageArt\Application\RateLimit\RateLimitedException;
use StageArt\Application\RateLimit\RateLimitRule;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Role\RoleKey;
use StageArt\Presentation\Rest\ParticipantInvitationRestController;
use StageArt\Presentation\Rest\RestFailureResponder;
use StageArt\Tests\Support\InMemoryRateLimiter;
use StageArt\Tests\Support\ParticipantConsentFixture;
use WP_REST_Request;

/**
 * PR #7 R14-B stage 8 (design doc §9.2, U4): the invitation rate limits.
 *
 * - create + resend: 30 an hour per operating Person, 100 a day per
 *   Production (fixed UTC windows); counted after the permission check and
 *   before the input check / transaction; the Production's window is not
 *   counted once the Person's is exceeded (both confirmed by the user).
 * - accept / reject: 10 failures (403 / 404) per Person per 10 minutes;
 *   read first, added to only on such a failure.
 * - Over a limit: 429 stageart_rate_limited with retry_after (seconds to
 *   the end of the window) and Retry-After; nothing of the operation done.
 */
final class InvitationRateLimitTest extends TestCase
{
    private ParticipantConsentFixture $f;
    private InMemoryRateLimiter $limiter;
    private InvitationRateLimit $rateLimit;
    private int $email = 0;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->limiter = new InMemoryRateLimiter();
        $this->limiter->now = new DateTimeImmutable('2026-11-18T10:20:00+00:00');
        $this->rateLimit = new InvitationRateLimit($this->limiter, $this->f->authorization, fn (): DateTimeImmutable => $this->limiter->now);
    }

    private function resendUseCase(): ResendParticipantInvitationUseCase
    {
        return new ResendParticipantInvitationUseCase($this->f->invitations, $this->f->productions, $this->f->authorization, $this->f->conditions(), $this->f->sendService(), $this->f->transactions, $this->rateLimit);
    }

    private function createUseCase(): CreateParticipantInvitationUseCase
    {
        return new CreateParticipantInvitationUseCase($this->f->productions, $this->f->authorization, $this->f->invitations, $this->resendUseCase(), $this->f->conditions(), $this->f->sendService(), $this->f->transactions, $this->rateLimit);
    }

    private function invite(?Person $as = null, ?string $email = null, ?string $productionId = null): void
    {
        $this->createUseCase()->execute(new CreateParticipantInvitationCommand(
            $productionId ?? $this->f->production->id()->toString(),
            ($as ?? $this->f->primaryManager)->wordPressUserId(),
            '招待 太郎',
            $email ?? 'guest' . (++$this->email) . '@example.com',
            'CAST'
        ));
    }

    private function personCount(Person $person): int
    {
        return $this->limiter->count(RateLimitRule::invitationSendPerPerson(), $person->id()->toString(), $this->limiter->now);
    }

    private function productionCount(): int
    {
        return $this->limiter->count(RateLimitRule::invitationSendPerProduction(), $this->f->production->id()->toString(), $this->limiter->now);
    }

    private function invitationCount(): int
    {
        return count($this->f->invitations->findByProductionId($this->f->production->id()));
    }

    // --- the rules ---------------------------------------------------------

    public function test_the_three_rules_match_section_9_2(): void
    {
        $person = RateLimitRule::invitationSendPerPerson();
        $production = RateLimitRule::invitationSendPerProduction();
        $failures = RateLimitRule::invitationRespondFailurePerPerson();

        $this->assertSame([30, 3600, 'invitation_create|person|P'], [$person->limit(), $person->windowSeconds(), $person->bucketName('P')]);
        $this->assertSame([100, 86400, 'invitation_create|production|X'], [$production->limit(), $production->windowSeconds(), $production->bucketName('X')]);
        $this->assertSame([10, 600, 'invitation_respond_failure|person|P'], [$failures->limit(), $failures->windowSeconds(), $failures->bucketName('P')]);
    }

    public function test_windows_are_fixed_utc_windows(): void
    {
        $hour = RateLimitRule::invitationSendPerPerson();
        $day = RateLimitRule::invitationSendPerProduction();
        $tenMinutes = RateLimitRule::invitationRespondFailurePerPerson();
        // 19:20:30 in Tokyo is 10:20:30 UTC.
        $now = new DateTimeImmutable('2026-11-18T19:20:30+09:00');

        $this->assertSame('2026-11-18T10:00:00+00:00', $hour->windowStart($now)->format(DATE_ATOM));
        $this->assertSame('2026-11-18T11:00:00+00:00', $hour->windowEnd($now)->format(DATE_ATOM));
        $this->assertSame('2026-11-18T00:00:00+00:00', $day->windowStart($now)->format(DATE_ATOM));
        $this->assertSame('2026-11-19T00:00:00+00:00', $day->windowEnd($now)->format(DATE_ATOM));
        $this->assertSame('2026-11-18T10:20:00+00:00', $tenMinutes->windowStart($now)->format(DATE_ATOM));
        $this->assertSame(2370, $hour->retryAfter($now));
        $this->assertSame(570, $tenMinutes->retryAfter($now));

        // The boundary: the last second belongs to the old window, the next to a new one.
        $last = new DateTimeImmutable('2026-11-18T10:59:59+00:00');
        $first = new DateTimeImmutable('2026-11-18T11:00:00+00:00');
        $this->assertSame('2026-11-18T10:00:00+00:00', $hour->windowStart($last)->format(DATE_ATOM));
        $this->assertSame(1, $hour->retryAfter($last));
        $this->assertSame('2026-11-18T11:00:00+00:00', $hour->windowStart($first)->format(DATE_ATOM));
        $this->assertSame(3600, $hour->retryAfter($first));
    }

    // --- create / resend ------------------------------------------------------

    public function test_the_31st_send_in_an_hour_is_429_and_does_nothing(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->invite();
        }

        $this->assertSame(30, $this->invitationCount());

        try {
            $this->invite();
            $this->fail('The 31st is over the limit');
        } catch (RateLimitedException $exception) {
            $this->assertSame(2400, $exception->retryAfter(), 'Seconds from 10:20 to the end of the 10:00 window');
        }

        $this->assertSame(30, $this->invitationCount(), 'No invitation, send or audit row for the refused attempt');
        $this->assertSame(31, $this->personCount($this->f->primaryManager), 'The refused attempt is counted too');
        $this->assertSame(30, $this->productionCount(), "Over the Person's limit: the Production's window is not counted");
    }

    public function test_resend_and_create_share_the_window(): void
    {
        $this->invite(null, 'same@example.com');
        $invitation = $this->f->invitations->findByProductionId($this->f->production->id())[0];

        for ($i = 0; $i < 4; $i++) {
            $this->resendUseCase()->execute(new ResendParticipantInvitationCommand($invitation->id()->toString(), $this->f->primaryManager->wordPressUserId()));
        }

        // A create that turns into a resend (same address and type) is one attempt.
        $this->invite(null, 'same@example.com');

        $this->assertSame(6, $this->personCount($this->f->primaryManager));
        $this->assertSame(6, $this->productionCount());
    }

    public function test_the_101st_send_of_a_production_in_a_day_is_429(): void
    {
        $managers = [$this->f->primaryManager];

        for ($i = 0; $i < 3; $i++) {
            $managers[] = $this->f->participantManager();
        }

        foreach ($managers as $manager) {
            for ($i = 0; $i < 25; $i++) {
                $this->invite($manager);
            }
        }

        $this->assertSame(100, $this->invitationCount());

        $another = $this->f->participantManager();

        try {
            $this->invite($another);
            $this->fail('The 101st of the day is over the Production limit');
        } catch (RateLimitedException $exception) {
            $this->assertSame(49200, $exception->retryAfter(), 'Seconds from 10:20 UTC to the end of the UTC day');
        }

        $this->assertSame(100, $this->invitationCount());
        $this->assertSame(1, $this->personCount($another), "Counted in the Person's own window too");
    }

    public function test_someone_without_permission_is_not_counted(): void
    {
        $outsider = $this->f->person('outsider@example.com');
        $rehearsal = $this->f->person('rehearsal@example.com');
        $this->f->participantRow($rehearsal, 'ACTIVE', 'STAFF', 'INVITATION', null, true);
        $this->f->delegate($rehearsal, RoleKey::REHEARSAL_MANAGER);

        foreach ([$outsider, $rehearsal] as $person) {
            for ($i = 0; $i < 40; $i++) {
                try {
                    $this->invite($person);
                    $this->fail('403 expected');
                } catch (ParticipantAccessDeniedException $expected) {
                    $this->addToAssertionCount(1);
                }
            }

            $this->assertSame(0, $this->personCount($person));
        }

        try {
            $this->invite(null, null, ProductionId::generate()->toString());
            $this->fail('404 expected');
        } catch (ProductionNotFoundException $expected) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, $this->personCount($this->f->primaryManager), 'Unknown production: not counted');
        $this->assertSame(0, $this->productionCount(), "An outsider cannot use up the Production's window");

        $invitation = $this->f->invitation('x@example.com');

        try {
            $this->resendUseCase()->execute(new ResendParticipantInvitationCommand($invitation->id()->toString(), $outsider->wordPressUserId()));
            $this->fail('403 expected');
        } catch (ParticipantInvitationAccessDeniedException $expected) {
            $this->assertSame(0, $this->productionCount());
        }
    }

    public function test_an_invalid_input_after_the_permission_check_is_counted(): void
    {
        try {
            $this->invite(null, 'not-an-address');
            $this->fail('422 expected');
        } catch (\InvalidArgumentException $expected) {
            $this->assertSame(1, $this->personCount($this->f->primaryManager));
            $this->assertSame(1, $this->productionCount());
        }
    }

    public function test_a_new_window_starts_fresh_and_up_to_twice_the_limit_passes_around_the_boundary(): void
    {
        $this->limiter->now = new DateTimeImmutable('2026-11-18T10:59:59+00:00');

        for ($i = 0; $i < 30; $i++) {
            $this->invite();
        }

        $this->limiter->now = new DateTimeImmutable('2026-11-18T11:00:00+00:00');

        for ($i = 0; $i < 30; $i++) {
            $this->invite();
        }

        $this->assertSame(60, $this->invitationCount(), 'Accepted by the design (fixed windows)');

        $this->expectException(RateLimitedException::class);
        $this->invite();
    }

    // --- accept / reject failures ----------------------------------------------

    private function accept(Person $as, string $invitationId): void
    {
        $this->rateLimit->guardResponse($as->wordPressUserId(), fn () => $this->f->accept()->execute(new AcceptParticipantInvitationCommand($as->wordPressUserId(), $invitationId)));
    }

    private function reject(Person $as, string $invitationId): void
    {
        $this->rateLimit->guardResponse($as->wordPressUserId(), fn () => $this->f->reject()->execute(new RejectParticipantInvitationCommand($as->wordPressUserId(), $invitationId)));
    }

    private function failureCount(Person $person): int
    {
        return $this->limiter->count(RateLimitRule::invitationRespondFailurePerPerson(), $person->id()->toString(), $this->limiter->now);
    }

    public function test_ten_failures_in_ten_minutes_then_429_even_for_a_valid_invitation(): void
    {
        $person = $this->f->person('taro@example.com');
        $someoneElses = $this->f->invitation('someone-else@example.com');
        $mine = $this->f->invitation('taro@example.com');

        for ($i = 0; $i < 10; $i++) {
            try {
                $i % 2 === 0 ? $this->accept($person, $someoneElses->id()->toString()) : $this->reject($person, 'not-a-uuid');
                $this->fail('404 expected');
            } catch (ParticipantInvitationNotFoundException $expected) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(10, $this->failureCount($person));

        try {
            $this->accept($person, $mine->id()->toString());
            $this->fail('At the limit the request is refused before anything is done');
        } catch (RateLimitedException $exception) {
            $this->assertSame(600, $exception->retryAfter(), "Seconds from 10:20 to the end of the 10:20 window");
        }

        $this->assertTrue($this->f->invitations->findById($mine->id())->isPending(), 'Nothing was done');
        $this->assertSame(10, $this->failureCount($person), 'The refused request is only read, not added');

        // The next 10-minute window starts fresh.
        $this->limiter->now = new DateTimeImmutable('2026-11-18T10:30:00+00:00');
        $this->accept($person, $mine->id()->toString());
        $this->assertFalse($this->f->invitations->findById($mine->id())->isPending());
    }

    public function test_403_failures_count_but_success_409_and_410_do_not(): void
    {
        $unverified = $this->f->person('unverified@example.com', false);
        $invitation = $this->f->invitation('unverified@example.com');

        try {
            $this->accept($unverified, $invitation->id()->toString());
            $this->fail('403 expected');
        } catch (InvitationRecipientNotEligibleException $expected) {
            $this->assertSame(1, $this->failureCount($unverified));
        }

        $person = $this->f->person('hanako@example.com');
        $accepted = $this->f->invitation('hanako@example.com');
        $this->accept($person, $accepted->id()->toString());

        try {
            $this->reject($person, $accepted->id()->toString());
            $this->fail('409 expected');
        } catch (ParticipantInvitationNotPendingException $expected) {
            $this->addToAssertionCount(1);
        }

        $expired = $this->f->invitation('hanako@example.com', null, 'STAFF', new DateTimeImmutable('-1 day'));

        try {
            $this->reject($person, $expired->id()->toString());
            $this->fail('410 expected');
        } catch (\StageArt\Application\ParticipantInvitation\ParticipantInvitationExpiredException $expected) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, $this->failureCount($person));
    }

    // --- HTTP answer --------------------------------------------------------------

    public function test_the_answer_is_429_rate_limited_with_retry_after(): void
    {
        require_once __DIR__ . "/../../Support/wordpress-rest-stubs.php";
        $response = RestFailureResponder::toResponse(new RateLimitedException(2400));

        $this->assertSame(429, $response->get_status());
        $this->assertSame(['code' => 'stageart_rate_limited', 'message' => 'Too many requests. Please try again later.', 'data' => ['status' => 429, 'retry_after' => 2400]], $response->get_data());
        $this->assertSame('2400', $response->get_headers()['Retry-After']);
    }

    public function test_the_controller_passes_the_429_on_from_accept(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
        $person = $this->f->person('jiro@example.com');
        $GLOBALS['stageart_test_wp'] = ['current_user' => $person->wordPressUserId()];
        $controller = new ParticipantInvitationRestController(
            $this->createUseCase(),
            $this->f->listInvitations(),
            $this->resendUseCase(),
            $this->f->cancelInvitation(),
            $this->f->accept(),
            $this->f->reject(),
            $this->f->listMine(),
            $this->rateLimit
        );
        $missing = ['id' => '00000000-0000-4000-8000-000000000000'];

        for ($i = 0; $i < 10; $i++) {
            $answer = $controller->accept(new WP_REST_Request($missing));
            $this->assertSame(404, $answer->get_error_data()['status']);
        }

        try {
            $controller->reject(new WP_REST_Request($missing));
            $this->fail('The 429 goes on to RestFailureResponder');
        } catch (RateLimitedException $exception) {
            $this->assertSame(429, RestFailureResponder::toResponse($exception)->get_status());
        }
    }
}
