<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\Timetable\NextTimetableVersionResolver;
use StageArt\Application\Timetable\TimetableVersionRequiredException;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\Timetable;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItem;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * Phase 3.5: Items can only ever be added to a DRAFT Version.
 *
 * - If a DRAFT already exists for the Rehearsal, the Item is added to
 *   it directly.
 * - If neither a DRAFT nor a PUBLISHED Version exists yet, this is the
 *   Rehearsal's very first Version - it is auto-created as an empty
 *   DRAFT (matching Phase 3's original find-or-create convenience;
 *   Timetable.md's "Timetableが不要なRehearsalも許可する" means there is
 *   still no separate "create Timetable" step for a brand new
 *   Rehearsal).
 * - If a PUBLISHED Version exists but no DRAFT does, this Item cannot
 *   be added here: per Timetable.md's "No Direct Overwrite Principle",
 *   changing a published schedule requires the deliberate "Create New
 *   Version" operation first (CreateNewTimetableVersionUseCase) -
 *   TimetableVersionRequiredException signals exactly this.
 */
final class CreateTimetableItemUseCase
{
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private TimetableRepositoryInterface $timetables;
    private TimetableItemRepositoryInterface $items;
    private TimetableItemTargetValidator $targetValidator;
    private NextTimetableVersionResolver $versionResolver;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        TimetableRepositoryInterface $timetables,
        TimetableItemRepositoryInterface $items,
        TimetableItemTargetValidator $targetValidator,
        NextTimetableVersionResolver $versionResolver,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->timetables = $timetables;
        $this->items = $items;
        $this->targetValidator = $targetValidator;
        $this->versionResolver = $versionResolver;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(CreateTimetableItemCommand $command): TimetableItemResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new TimetableItemAccessDeniedException('No StageArt Person is linked to this WordPress user.');
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

        if (! $this->authorization->hasProductionCapability($requester, $production, RehearsalCapability::MANAGE)) {
            throw new TimetableItemAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can manage this Timetable.'
            );
        }

        $draft = $this->timetables->findDraftByRehearsalId($rehearsalId);

        if ($draft === null && $this->timetables->findPublishedByRehearsalId($rehearsalId) !== null) {
            throw new TimetableVersionRequiredException(
                'This Rehearsal already has a PUBLISHED Timetable. Create a new Version before adding Items.'
            );
        }

        $participantType = $command->participantType !== null ? ParticipantType::fromString($command->participantType) : null;
        $targetPersonIds = array_map(
            static fn (string $id): PersonId => PersonId::fromString($id),
            $command->targetPersonIds
        );
        $this->targetValidator->assertValidTargets($production, $targetPersonIds);

        $startDateTime = $this->parseRequiredDateTime($command->startDateTime);
        $endDateTime = $this->parseOptionalDateTime($command->endDateTime);

        $item = $this->transactions->run(
            function () use (
                $rehearsalId,
                $requester,
                $draft,
                $command,
                $participantType,
                $targetPersonIds,
                $startDateTime,
                $endDateTime
            ): TimetableItem {
                $timetable = $draft;

                if ($timetable === null) {
                    $version = $this->versionResolver->resolve($rehearsalId);
                    $timetable = Timetable::create($rehearsalId, $version, $requester->id());
                    $this->timetables->save($timetable);
                }

                $item = TimetableItem::create(
                    $timetable->id(),
                    $command->title,
                    $command->description,
                    $startDateTime,
                    $endDateTime,
                    $command->displayOrder,
                    $command->category,
                    $command->venue,
                    $participantType,
                    $targetPersonIds,
                    $command->notes
                );

                $this->items->save($item);

                return $item;
            }
        );

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
