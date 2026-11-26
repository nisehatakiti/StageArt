<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Reservation\ReservationResult;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * CheckIn.md "# Search": "Reservation Number" / "Booker Name" at minimum
 * - distinct from the public self-service lookup
 * (`GetReservationByNumberUseCase`, exact number + email match, no
 * authentication) since reception staff need to browse/disambiguate
 * multiple candidates, not just confirm one booking they already know.
 * A Performance's Reservation list is expected to stay small enough
 * (single-show attendance, not a whole Production's history) that
 * in-memory keyword filtering over `findByPerformanceId()` needs no new
 * indexed SQL query - disclosed as a V1 scale assumption.
 */
final class SearchReservationsForCheckInUseCase
{
    private ReservationRepositoryInterface $reservations;
    private PerformanceRepositoryInterface $performances;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->reservations = $reservations;
        $this->performances = $performances;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    /**
     * @return ReservationResult[]
     */
    public function execute(SearchReservationsForCheckInQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new CheckInAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $performanceId = PerformanceId::fromString($query->performanceId);
        $performance = $this->performances->findById($performanceId);

        if (! $performance) {
            throw new PerformanceNotFoundException($query->performanceId);
        }

        if (! $this->authorization->canForProduction($requesterId, $performance->productionId(), CheckInCapability::MANAGE)) {
            throw new CheckInAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the CHECKIN_MANAGER Role can search Reservations for Check-in.'
            );
        }

        $keyword = $query->keyword !== null ? trim($query->keyword) : '';
        $reservations = $this->reservations->findByPerformanceId($performanceId);

        if ($keyword !== '') {
            $reservations = array_values(array_filter(
                $reservations,
                static fn (Reservation $r): bool => str_contains($r->reservationNumber()->toString(), $keyword)
                    || mb_stripos($r->bookerName(), $keyword) !== false
            ));
        }

        return array_map(static fn (Reservation $r): ReservationResult => ReservationResult::fromDomain($r), $reservations);
    }
}
