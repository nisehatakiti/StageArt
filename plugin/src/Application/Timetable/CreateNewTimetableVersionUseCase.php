<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\Timetable;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItem;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * "Create New Version" (Phase 3.5 instruction §5): the only way to
 * change a PUBLISHED Timetable's content is to create a new DRAFT
 * Version copying the PUBLISHED Version's Items, edit the copy, then
 * Publish it. The copy uses fresh TimetableItemIds - copied Items never
 * share Identity with the source Items (same principle as the existing
 * "Timetable Item Copy" section). Editing the new DRAFT never touches
 * the source Version's rows.
 *
 * At most one DRAFT may exist per Rehearsal at a time (Timetable.md's
 * "Draft" section) - this UseCase rejects if one already exists rather
 * than silently reusing or replacing it, keeping "which Draft becomes
 * the next Version" unambiguous.
 */
final class CreateNewTimetableVersionUseCase
{
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private TimetableRepositoryInterface $timetables;
    private TimetableItemRepositoryInterface $items;
    private NextTimetableVersionResolver $versionResolver;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        TimetableRepositoryInterface $timetables,
        TimetableItemRepositoryInterface $items,
        NextTimetableVersionResolver $versionResolver,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->timetables = $timetables;
        $this->items = $items;
        $this->versionResolver = $versionResolver;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(CreateNewTimetableVersionCommand $command): TimetableResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new TimetableAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsalId = RehearsalId::fromString($command->rehearsalId);
        $rehearsal = $this->rehearsals->findById($rehearsalId);

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($command->rehearsalId);
        }

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->canManageRehearsals($requester, $production)) {
            throw new TimetableAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can create a new Timetable Version.'
            );
        }

        if ($this->timetables->findDraftByRehearsalId($rehearsalId) !== null) {
            throw new TimetableDraftAlreadyExistsException(
                'A DRAFT Timetable Version already exists for this Rehearsal. Edit or publish it before creating another.'
            );
        }

        $published = $this->timetables->findPublishedByRehearsalId($rehearsalId);

        $draft = $this->transactions->run(function () use ($rehearsalId, $requester, $published): Timetable {
            $nextVersion = $this->versionResolver->resolve($rehearsalId);
            $draft = Timetable::create($rehearsalId, $nextVersion, $requester->id());
            $this->timetables->save($draft);

            if ($published !== null) {
                foreach ($this->items->findByTimetableId($published->id()) as $sourceItem) {
                    $copy = TimetableItem::create(
                        $draft->id(),
                        $sourceItem->title(),
                        $sourceItem->description(),
                        $sourceItem->startDateTime(),
                        $sourceItem->endDateTime(),
                        $sourceItem->displayOrder(),
                        $sourceItem->category(),
                        $sourceItem->venue(),
                        $sourceItem->participantType(),
                        $sourceItem->targetPersonIds(),
                        $sourceItem->notes()
                    );
                    $this->items->save($copy);
                }
            }

            return $draft;
        });

        return TimetableResult::fromDomain($draft);
    }
}
