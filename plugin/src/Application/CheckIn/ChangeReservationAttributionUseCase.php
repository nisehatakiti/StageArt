<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Reservation\ReservationNotFoundException;
use StageArt\Application\Reservation\ReservationResult;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * Corrects which Production Member (§ "誰扱い") a Reservation's sales
 * performance counts toward - see Reservation::changeAttribution()'s own
 * docblock for why this is separate from createdBy/bookerName. Gated by
 * the same CheckInCapability::MANAGE as the rest of the reception
 * workflow, since this is the kind of correction reception staff make
 * (e.g. assigning an initially-unattributed walk-up sale to the member
 * who actually made it), not a Settlement/Accounting-level operation.
 *
 * PR #7 review: the attributed Person must be a member of the
 * Reservation's own Production - the same rule the walk-up sale applies
 * (Phase 0-4統合監査 P1-4, Chapter 31 §3.1) - or a sale could be counted
 * toward someone outside it. Clearing the attribution (null) needs no
 * check.
 */
final class ChangeReservationAttributionUseCase
{
    private ReservationRepositoryInterface $reservations;
    private PerformanceRepositoryInterface $performances;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private MembershipContract $membership;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        MembershipContract $membership
    ) {
        $this->reservations = $reservations;
        $this->performances = $performances;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->membership = $membership;
    }

    public function execute(ChangeReservationAttributionCommand $command): ReservationResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new CheckInAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $reservation = $this->reservations->findById(ReservationId::fromString($command->reservationId));

        if (! $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $performance = $this->performances->findById($reservation->performanceId());

        if (! $performance) {
            throw new PerformanceNotFoundException($reservation->performanceId()->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $performance->productionId(), CheckInCapability::MANAGE)) {
            throw new CheckInAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the CHECKIN_MANAGER Role can change a Reservation\'s attribution.'
            );
        }

        $attributedPersonId = $command->attributedPersonId !== null
            ? PersonId::fromString($command->attributedPersonId)
            : null;

        if ($attributedPersonId !== null && ! $this->membership->isProductionMember($attributedPersonId, $performance->productionId())) {
            throw new AttributedPersonNotProductionMemberException();
        }

        $reservation->changeAttribution($attributedPersonId, $requesterId);
        $this->reservations->save($reservation);

        return ReservationResult::fromDomain($reservation);
    }
}
