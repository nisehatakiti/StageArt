<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;

/**
 * Read access to the in-progress DRAFT Version, if one exists. Kept at
 * the same isProductionMember() level as the PUBLISHED view (Timetable
 * Authorization's Read Permission does not distinguish Draft vs
 * Published) - only editing the Draft is management-restricted (see
 * CreateTimetableItemUseCase / UpdateTimetableItemUseCase).
 */
final class GetDraftTimetableUseCase
{
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(GetDraftTimetableQuery $query): TimetableResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new TimetableAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsalId = RehearsalId::fromString($query->rehearsalId);
        $rehearsal = $this->rehearsals->findById($rehearsalId);

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($query->rehearsalId);
        }

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new TimetableAccessDeniedException('You must be a member of this Production to view this Timetable.');
        }

        $timetable = $this->timetables->findDraftByRehearsalId($rehearsalId);

        if (! $timetable) {
            throw new TimetableNotFoundException($query->rehearsalId);
        }

        return TimetableResult::fromDomain($timetable);
    }
}
