<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\Timetable\TimetableVersionNotEditableException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class UpdateTimetableItemUseCase
{
    private TimetableItemRepositoryInterface $items;
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private TimetableItemTargetValidator $targetValidator;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        TimetableItemRepositoryInterface $items,
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        TimetableItemTargetValidator $targetValidator,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->items = $items;
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->targetValidator = $targetValidator;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(UpdateTimetableItemCommand $command): TimetableItemResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
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

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, RehearsalCapability::MANAGE)) {
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
        $this->targetValidator->assertValidTargets($productionId, $targetPersonIds);

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
