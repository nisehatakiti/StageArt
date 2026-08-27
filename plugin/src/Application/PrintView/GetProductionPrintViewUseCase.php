<?php

declare(strict_types=1);

namespace StageArt\Application\PrintView;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\TimetableItem\TimetableItemResult;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItem;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * Print View's data-aggregation step (Timetable.md's "Print View
 * Implementation Decisions"): a pure Read Operation, structurally
 * identical in spirit to ListProductionTimetableItemsUseCase but
 * grouped per-Rehearsal rather than flattened, because Print View
 * must show each Rehearsal's own Version/Published-at (§18) - something
 * a flat cross-Rehearsal item list cannot carry.
 *
 * Like ListProductionTimetableItemsUseCase: only each Rehearsal's
 * current PUBLISHED Timetable is used (never DRAFT); a Rehearsal with
 * no current Published Version is simply omitted, matching "通常の
 * Production Scheduleと同じ扱い" (§4); no Role/Person filtering exists
 * anywhere in this class (§6/§16) - every Production Member gets back
 * the identical structure.
 *
 * Uses the bulk findPublishedByRehearsalIds()/findByTimetableIds()
 * repository methods instead of looping
 * findPublishedByRehearsalId()/findByTimetableId() per Rehearsal, per
 * §26's explicit N+1 avoidance requirement.
 *
 * StageArt Core/Module Architecture Phase 3: migrated from
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService` to
 * Core Contracts - PrintView is owned by the Rehearsal Module
 * (`PrintViewRestController` is listed as Rehearsal's own in
 * `docs/architecture/CoreModuleArchitecture.md` §10), so it should not
 * depend on Core internals directly either.
 */
final class GetProductionPrintViewUseCase
{
    private ProductionContextContract $productionContext;
    private RehearsalRepositoryInterface $rehearsals;
    private TimetableRepositoryInterface $timetables;
    private TimetableItemRepositoryInterface $items;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        ProductionContextContract $productionContext,
        RehearsalRepositoryInterface $rehearsals,
        TimetableRepositoryInterface $timetables,
        TimetableItemRepositoryInterface $items,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->productionContext = $productionContext;
        $this->rehearsals = $rehearsals;
        $this->timetables = $timetables;
        $this->items = $items;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    public function execute(ProductionPrintViewQuery $query): ProductionPrintViewResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new PrintViewAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new PrintViewAccessDeniedException(
                'You must be a member of this Production to view its Print View.'
            );
        }

        $rehearsals = $this->rehearsals->findByProductionId($productionId);
        $rehearsalIds = array_map(static fn ($rehearsal) => $rehearsal->id(), $rehearsals);

        $publishedByRehearsalId = $this->timetables->findPublishedByRehearsalIds($rehearsalIds);
        $timetableIds = array_map(static fn ($timetable) => $timetable->id(), array_values($publishedByRehearsalId));
        $allItems = $this->items->findByTimetableIds($timetableIds);

        $itemsByTimetableId = [];
        foreach ($allItems as $item) {
            $itemsByTimetableId[$item->timetableId()->toString()][] = $item;
        }

        $sections = [];
        foreach ($rehearsals as $rehearsal) {
            $timetable = $publishedByRehearsalId[$rehearsal->id()->toString()] ?? null;

            if ($timetable === null) {
                continue;
            }

            $itemsForTimetable = $itemsByTimetableId[$timetable->id()->toString()] ?? [];

            usort(
                $itemsForTimetable,
                static fn (TimetableItem $a, TimetableItem $b): int => $a->startDateTime() <=> $b->startDateTime()
            );

            $sections[] = new RehearsalPrintSectionResult(
                $rehearsal->id()->toString(),
                $rehearsal->title(),
                $rehearsal->startDateTime() !== null ? $rehearsal->startDateTime()->format(DATE_ATOM) : null,
                $timetable->version(),
                $timetable->publishedAt() !== null ? $timetable->publishedAt()->format(DATE_ATOM) : null,
                $timetable->changeSummary(),
                array_map(static fn (TimetableItem $item) => TimetableItemResult::fromDomain($item), $itemsForTimetable)
            );
        }

        usort(
            $sections,
            static function (RehearsalPrintSectionResult $a, RehearsalPrintSectionResult $b): int {
                return ($a->rehearsalStartDateTime ?? '') <=> ($b->rehearsalStartDateTime ?? '');
            }
        );

        return new ProductionPrintViewResult($productionId->toString(), $production->name, $sections);
    }
}
