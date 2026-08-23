<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\Timetable\TimetableVersionNotEditableException;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

final class UpdateTimetableItemUseCase
{
    private TimetableItemRepositoryInterface $items;
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private TimetableItemTargetValidator $targetValidator;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        TimetableItemRepositoryInterface $items,
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        TimetableItemTargetValidator $targetValidator,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->items = $items;
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->targetValidator = $targetValidator;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(UpdateTimetableItemCommand $command): TimetableItemResult
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

        if (! $this->authorization->canManageRehearsals($requester, $production)) {
            throw new TimetableItemAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can manage this Timetable.'
            );
        }

        if (! $timetable->isDraft()) {
            throw new TimetableVersionNotEditableException(
                'Only a DRAFT Timetable Version can be edited. This Item belongs to a ' . $timetable->status()->toString() . ' Version.'
            );
        }

        $participantType = $command->participantType !== null ? ParticipantType::fromString($command->participantType) : null;
        $targetPersonIds = array_map(
            static fn (string $id): PersonId => PersonId::fromString($id),
            $command->targetPersonIds
        );
        $this->targetValidator->assertValidTargets($production, $targetPersonIds);

        $item->update(
            $command->title,
            $command->description,
            $this->parseRequiredDateTime($command->startDateTime),
            $this->parseOptionalDateTime($command->endDateTime),
            $command->displayOrder,
            $command->category,
            $command->venue,
            $participantType,
            $targetPersonIds,
            $command->notes
        );

        $this->transactions->run(function () use ($item): void {
            $this->items->save($item);
        });

        return TimetableItemResult::fromDomain($item);
    }

    private function parseRequiredDateTime(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid date/time value: {$value}");
        }
    }

    private function parseOptionalDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->parseRequiredDateTime($value);
    }
}
