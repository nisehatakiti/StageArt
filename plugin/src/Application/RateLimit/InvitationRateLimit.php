<?php

declare(strict_types=1);

namespace StageArt\Application\RateLimit;

use DateTimeImmutable;
use StageArt\Application\OrganizationInvitation\OrganizationInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\InvitationRecipientNotEligibleException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * PR #7 R14-B stage 8 (design doc §9.2): the three invitation rate limits.
 *
 * Create / resend (assertSendAllowed), called by the UseCases after the
 * requester's permission on the Production is confirmed and before the
 * input is validated or the transaction starts (confirmed by the user before
 * stage 8: someone without permission cannot use up a Production's
 * window; an invalid input is still an attempt):
 * - per operating Person, 30 an hour; checked first - when it is exceeded
 *   the Production's window is not counted (confirmed by the user: one
 *   Person cannot use up the Production's window for the other managers);
 * - per Production, 100 a day.
 * Every attempt is counted, including the ones over the limit (safe side).
 *
 * accept / reject failures (guardResponse): per Person, 10 failures (403 /
 * 404) per 10 minutes. The current count is read first (not added to);
 * at the limit the request is refused with 429 before anything is done.
 * Only a failure answering 403 / 404 adds to the count - a success, 409 or
 * 410 does not. Between the read and the add a few parallel requests may
 * pass (a few over the limit, never fewer counted) - as the design accepts.
 */
final class InvitationRateLimit
{
    private RateLimiterInterface $limiter;
    private ProductionAuthorizationService $authorization;
    /** @var callable(): DateTimeImmutable */
    private $clock;

    /**
     * @param (callable(): DateTimeImmutable)|null $clock the current time; injectable for tests
     */
    public function __construct(RateLimiterInterface $limiter, ProductionAuthorizationService $authorization, ?callable $clock = null)
    {
        $this->limiter = $limiter;
        $this->authorization = $authorization;
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * @throws RateLimitedException
     */
    public function assertSendAllowed(PersonId $requester, ProductionId $production, ?DateTimeImmutable $now = null): void
    {
        $now = $now ?? ($this->clock)();
        $perPerson = RateLimitRule::invitationSendPerPerson();

        if ($this->limiter->hit($perPerson, $requester->toString(), $now) > $perPerson->limit()) {
            throw new RateLimitedException($perPerson->retryAfter($now));
        }

        $perProduction = RateLimitRule::invitationSendPerProduction();

        if ($this->limiter->hit($perProduction, $production->toString(), $now) > $perProduction->limit()) {
            throw new RateLimitedException($perProduction->retryAfter($now));
        }
    }

    /**
     * Runs an invitee request (opening an invitation from the mail -
     * GET /me/participant-invitations/{id} and
     * GET /me/organization-invitations/{id}; the accept / reject it
     * guarded before were removed, invitation policy 2026-11-27) for the
     * logged-in WordPress user under the failure limit. A WordPress user without a StageArt Person has nothing
     * to count against (the operation refuses them anyway).
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws RateLimitedException
     */
    public function guardResponse(int $wordPressUserId, callable $operation, ?DateTimeImmutable $now = null)
    {
        $person = $this->authorization->resolveCurrentPerson($wordPressUserId);

        if ($person === null) {
            return $operation();
        }

        $rule = RateLimitRule::invitationRespondFailurePerPerson();
        $subject = $person->id()->toString();
        $at = $now ?? ($this->clock)();

        if ($this->limiter->count($rule, $subject, $at) >= $rule->limit()) {
            throw new RateLimitedException($rule->retryAfter($at));
        }

        try {
            return $operation();
        } catch (InvitationRecipientNotEligibleException | ParticipantInvitationNotFoundException | OrganizationInvitationNotFoundException $failure) {
            $this->limiter->hit($rule, $subject, $now ?? ($this->clock)());

            throw $failure;
        }
    }
}
