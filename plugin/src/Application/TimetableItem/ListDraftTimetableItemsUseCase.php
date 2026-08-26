<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * Lists the in-progress DRAFT Version's Items - needed while editing,
 * before Publish. Read access matches the PUBLISHED view
 * (isProductionMember), not management-restricted (Timetable
 * Authorization's Read Permission does not distinguish Draft vs
 * Published).
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class ListDraftTimetableItemsUseCase
{
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private TimetableRepositoryInterface $timetables;
    private TimetableItemRepositoryInterface $items;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        TimetableRepositoryInterface $timetables,
        TimetableItemRepositoryInterface $items,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->timetables = $timetables;
        $this->items = $items;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    /**
     * @return TimetableItemResult[]
     */
    public function execute(ListDraftTimetableItemsQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TimetableItemAccessDeniedException('No StageArt Person is linked to this WordPress user.');
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
            throw new TimetableItemAccessDeniedException('You must be a member of this Production to view its Timetable.');
        }

        $timetable = $this->timetables->findDraftByRehearsalId($rehearsalId);

        if ($timetable === null) {
            return [];
        }

        return array_map(
            static fn ($item) => TimetableItemResult::fromDomain($item),
            $this->items->findByTimetableId($timetable->id())
        );
    }
}
