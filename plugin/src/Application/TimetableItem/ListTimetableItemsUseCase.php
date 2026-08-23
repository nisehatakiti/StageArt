<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * Shared Visibility Principle (Timetable.md): every Production Member
 * receives the exact same TimetableItem set for a given Rehearsal,
 * regardless of their own Participant Type or Role. This UseCase must
 * NEVER filter the returned items by the requester's own
 * ParticipantType/Role - doing so would silently recreate the "Role별
 * Timetable" design the Blueprint explicitly prohibits. The only
 * filter applied is the Production Scope membership check itself
 * (isProductionMember), which is identical for every caller regardless
 * of which Role they hold.
 *
 * Phase 3.5: this lists the current PUBLISHED Version's Items only (the
 * official schedule). Use ListDraftTimetableItemsUseCase to view the
 * in-progress DRAFT.
 */
final class ListTimetableItemsUseCase
{
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private TimetableRepositoryInterface $timetables;
    private TimetableItemRepositoryInterface $items;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        TimetableRepositoryInterface $timetables,
        TimetableItemRepositoryInterface $items,
        ProductionAuthorizationService $authorization
    ) {
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->timetables = $timetables;
        $this->items = $items;
        $this->authorization = $authorization;
    }

    /**
     * @return TimetableItemResult[]
     */
    public function execute(ListTimetableItemsQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new TimetableItemAccessDeniedException('No StageArt Person is linked to this WordPress user.');
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
            throw new TimetableItemAccessDeniedException('You must be a member of this Production to view its Timetable.');
        }

        $timetable = $this->timetables->findPublishedByRehearsalId($rehearsalId);

        if ($timetable === null) {
            return [];
        }

        return array_map(
            static fn ($item) => TimetableItemResult::fromDomain($item),
            $this->items->findByTimetableId($timetable->id())
        );
    }
}
