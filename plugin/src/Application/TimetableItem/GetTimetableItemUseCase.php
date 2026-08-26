<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class GetTimetableItemUseCase
{
    private TimetableItemRepositoryInterface $items;
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        TimetableItemRepositoryInterface $items,
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->items = $items;
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    public function execute(GetTimetableItemQuery $query): TimetableItemResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TimetableItemAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $item = $this->items->findById(TimetableItemId::fromString($query->timetableItemId));

        if (! $item) {
            throw new TimetableItemNotFoundException($query->timetableItemId);
        }

        $timetable = $this->timetables->findById($item->timetableId());

        if (! $timetable) {
            throw new TimetableNotFoundException($item->timetableId()->toString());
        }

        $rehearsal = $this->rehearsals->findById($timetable->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($timetable->rehearsalId()->toString());
        }

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new TimetableItemAccessDeniedException('You must be a member of this Production to view this TimetableItem.');
        }

        return TimetableItemResult::fromDomain($item);
    }
}
