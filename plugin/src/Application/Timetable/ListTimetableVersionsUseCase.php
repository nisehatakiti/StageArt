<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\Timetable;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;

/**
 * Version history: every DRAFT/PUBLISHED/ARCHIVED row for a Rehearsal,
 * newest version first. History is never deleted (Timetable.md: "旧
 * Published版は削除しない"), so this list only grows.
 */
final class ListTimetableVersionsUseCase
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

    /**
     * @return TimetableResult[]
     */
    public function execute(ListTimetableVersionsQuery $query): array
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
            throw new TimetableAccessDeniedException('You must be a member of this Production to view its Timetable Version history.');
        }

        $versions = $this->timetables->findByRehearsalId($rehearsalId);

        usort($versions, static fn (Timetable $a, Timetable $b): int => $b->version() <=> $a->version());

        return array_map(static fn (Timetable $timetable) => TimetableResult::fromDomain($timetable), $versions);
    }
}
