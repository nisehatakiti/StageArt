<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;

/**
 * Phase 3.5: "the Timetable" for a Rehearsal now means its current
 * PUBLISHED Version specifically (the official schedule) - not "the"
 * only Timetable row, since a Rehearsal can have many Version rows.
 * Use GetDraftTimetableUseCase for the in-progress DRAFT, and
 * ListTimetableVersionsUseCase for the full history.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class GetTimetableUseCase
{
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    public function execute(GetTimetableQuery $query): TimetableResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TimetableAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsalId = RehearsalId::fromString($query->rehearsalId);
        $rehearsal = $this->rehearsals->findById($rehearsalId);

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($query->rehearsalId);
        }

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new TimetableAccessDeniedException('You must be a member of this Production to view this Timetable.');
        }

        $timetable = $this->timetables->findPublishedByRehearsalId($rehearsalId);

        if (! $timetable) {
            throw new TimetableNotFoundException($query->rehearsalId);
        }

        return TimetableResult::fromDomain($timetable);
    }
}
