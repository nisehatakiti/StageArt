<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * §24/§30/§32: the admin-side view - "誰が予約しているか" for a
 * Performance, gated by the WordPress-authenticated standard
 * Capability path (PrimaryManager or a RESERVATION_MANAGER Delegate),
 * unlike every public self-service UseCase in this Module.
 */
final class ListReservationsUseCase
{
    private ReservationRepositoryInterface $reservations;
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->reservations = $reservations;
        $this->performances = $performances;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    /**
     * @return ReservationResult[]
     */
    public function execute(ListReservationsForPerformanceQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ReservationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $performanceId = PerformanceId::fromString($query->performanceId);
        $performance = $this->performances->findById($performanceId);

        if (! $performance) {
            throw new PerformanceNotFoundException($query->performanceId);
        }

        $production = $this->productionContext->getProduction($performance->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($performance->productionId()->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $performance->productionId(), ReservationCapability::MANAGE)) {
            throw new ReservationAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the RESERVATION_MANAGER Role can view this Performance\'s Reservations.'
            );
        }

        return array_map(
            static fn ($reservation) => ReservationResult::fromDomain($reservation),
            $this->reservations->findByPerformanceId($performanceId)
        );
    }
}
