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
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class ListTimetableItemsUseCase
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
    public function execute(ListTimetableItemsQuery $query): array
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
