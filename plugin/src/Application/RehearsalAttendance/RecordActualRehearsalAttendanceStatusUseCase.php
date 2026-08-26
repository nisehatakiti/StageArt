<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;

/**
 * Recording the day-of actual result (ATTENDED/LATE/ABSENT) is
 * management-only, not self-service: RehearsalAttendance.md draws a hard
 * line between a Person's own prior intent (ATTENDING/NOT_ATTENDING, via
 * RespondRehearsalAttendanceUseCase) and the actual outcome, which only
 * the PrimaryManager or a REHEARSAL_MANAGER Delegate may record - see
 * AuthorizationContract::canForProduction() with RehearsalCapability::MANAGE.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class RecordActualRehearsalAttendanceStatusUseCase
{
    private RehearsalAttendanceRepositoryInterface $attendances;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        RehearsalAttendanceRepositoryInterface $attendances,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->attendances = $attendances;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(RecordActualRehearsalAttendanceStatusCommand $command): RehearsalAttendanceResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new RehearsalAttendanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $attendance = $this->attendances->findById(RehearsalAttendanceId::fromString($command->rehearsalAttendanceId));

        if (! $attendance) {
            throw new RehearsalAttendanceNotFoundException($command->rehearsalAttendanceId);
        }

        $rehearsal = $this->rehearsals->findById($attendance->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($attendance->rehearsalId()->toString());
        }

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, RehearsalCapability::MANAGE)) {
            throw new RehearsalAttendanceAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can record the actual attendance result.'
            );
        }

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString($command->status));
        $this->attendances->save($attendance);

        return RehearsalAttendanceResult::fromDomain($attendance);
    }
}
