<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\Timetable\TimetableVersionNotEditableException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * Delete authorization is not called out separately from Create/Update
 * in Timetable.md ("Timetableの作成・変更・公開は、稽古管理権限を持つ
 * Personが行う" covers create/change/publish only, in that literal
 * wording) - this is a disclosed extension, following Rehearsal
 * Authorization's precedent (Rehearsal.Read/Create/Update/Delete are
 * all governed by the same Role) rather than inventing a separate,
 * looser delete rule the Blueprint never states.
 */
final class DeleteTimetableItemUseCase
{
    private TimetableItemRepositoryInterface $items;
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        TimetableItemRepositoryInterface $items,
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->items = $items;
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(DeleteTimetableItemCommand $command): void
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new TimetableItemAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $item = $this->items->findById(TimetableItemId::fromString($command->timetableItemId));

        if (! $item) {
            throw new TimetableItemNotFoundException($command->timetableItemId);
        }

        $timetable = $this->timetables->findById($item->timetableId());

        if (! $timetable) {
            throw new TimetableNotFoundException($item->timetableId()->toString());
        }

        $rehearsal = $this->rehearsals->findById($timetable->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($timetable->rehearsalId()->toString());
        }

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->hasProductionCapability($requester, $production, RehearsalCapability::MANAGE)) {
            throw new TimetableItemAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can manage this Timetable.'
            );
        }

        if (! $timetable->isDraft()) {
            throw new TimetableVersionNotEditableException(
                'Only a DRAFT Timetable Version can be edited. This Item belongs to a ' . $timetable->status()->toString() . ' Version.'
            );
        }

        $this->items->delete($item->id());
    }
}
