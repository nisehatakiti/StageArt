<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Performance\Performance;
use StageArt\Application\Performance\CapacityBelowReservationsException;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Performance\PerformanceStatus;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Production\ProductionSlug;

/**
 * Basic-info updates (Name only) are PrimaryManager-exclusive (see
 * ProductionAuthorizationService::canManageProduction) - no enumerated
 * ProductionDelegate Role grants Production.Update in this phase.
 *
 * Phase 6.1: no longer touches Status. See UpdateProductionCommand's
 * docblock - Status changes go through the dedicated Lifecycle Action
 * UseCases instead.
 *
 * Phase 2 Performance基盤 §11/§12: depends directly on
 * `Domain\Performance\PerformanceRepositoryInterface` (a Domain-layer
 * interface, not any Performance Module Application/Core-Contract type)
 * per the instruction's own explicit direction - "定員一括上書きの業務処理
 * は、Production更新のApplication UseCase内でRepositoryを利用して処理する
 * 構成を基本としてください". Production save and the full Performance
 * capacity cascade run inside one `TransactionManagerInterface::run()`
 * call so a failure partway through never leaves Production's own
 * capacity and its Performances' capacities inconsistent (§12).
 */
final class UpdateProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private PerformanceRepositoryInterface $performances;
    private TransactionManagerInterface $transactions;
    private ReservationRepositoryInterface $reservations;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        PerformanceRepositoryInterface $performances,
        TransactionManagerInterface $transactions,
        ReservationRepositoryInterface $reservations
    ) {
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->performances = $performances;
        $this->transactions = $transactions;
        $this->reservations = $reservations;
    }

    /**
     * PR #7 review (R6): the seats a Performance's Reservations take (every
     * one not CANCELLED, as the booking capacity check counts them), read
     * with FOR UPDATE after the Production lock (order Production ->
     * Performance -> Reservation).
     */
    private function occupiedSeats(Performance $performance): int
    {
        return array_sum(array_map(
            static fn (Reservation $r): int => $r->occupiesCapacity() ? $r->guestCount() : 0,
            $this->reservations->findByPerformanceIdForUpdate($performance->id())
        ));
    }

    public function execute(UpdateProductionCommand $command): ProductionResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageProduction($person, $production)) {
            throw new ProductionAccessDeniedException('Only the PrimaryManager can update this Production.');
        }

        // PR #7 review (R5): the form is applied to the Production as re-read
        // under the exclusive lock, in the transaction that saves it and
        // cascades capacity / duration to its Performances. save() writes
        // every column, so saving the copy read above could put back a
        // status or a Ticket Back / sales setting committed since (e.g.
        // reopen a Production that Completion had just closed). Holding the
        // exclusive lock also orders the cascade after the bookings in
        // progress, which hold the shared lock and read capacity under it,
        // and after Performance edits / cancellations (shared lock too), so
        // the cascade never overwrites one of those with an older copy.
        $production = $this->transactions->run(function () use ($production, $person, $command): Production {
            $production = $this->productions->findByIdForUpdate($production->id());

            if (! $production) {
                throw new ProductionNotFoundException($command->productionId);
            }

            if (! $this->authorization->canManageProduction($person, $production)) {
                throw new ProductionAccessDeniedException('Only the PrimaryManager can update this Production.');
            }

            $production->rename(new ProductionName($command->name));
            $production->changeTitleHeading($command->titleHeading);

            if ($command->slug !== null) {
                $newSlug = new ProductionSlug($command->slug);
                $currentSlug = $production->slug();

                if ($currentSlug === null || ! $currentSlug->equals($newSlug)) {
                    $existing = $this->productions->findBySlug($newSlug->toString());

                    if ($existing !== null && ! $existing->id()->equals($production->id())) {
                        throw new ProductionSlugAlreadyTakenException($newSlug->toString());
                    }

                    $production->changeSlug($newSlug);
                }
            }

            if ($command->published === true) {
                $production->publish($this->parseOptionalDateTime($command->publishedAt));
            } elseif ($command->published === false) {
                $production->unpublish();
            }

            $production->updateDescription($command->description, $this->parseOptionalDateTime($command->descriptionPublishedAt));
            $production->updateFlyer($command->flyerUrl, $this->parseOptionalDateTime($command->flyerPublishedAt));
            $production->updateVenue($command->venueName, $this->parseOptionalDateTime($command->venuePublishedAt));
            $production->updateSchedule(
                $this->parseOptionalDateTime($command->scheduleStartDate),
                $this->parseOptionalDateTime($command->scheduleEndDate),
                $this->parseOptionalDateTime($command->schedulePublishedAt)
            );
            $production->updateScriptDirection(
                $command->scriptCredit,
                $command->directionCredit,
                $this->parseOptionalDateTime($command->scriptDirectionPublishedAt)
            );
            $production->updateMemberInfoPublishedAt($this->parseOptionalDateTime($command->memberInfoPublishedAt));
            $production->updatePerformanceCommonRemarks($command->performanceCommonRemarks);

            $previousDuration = $production->performanceDurationMinutes();
            $production->updatePerformanceDurationMinutes($command->performanceDurationMinutes);
            $newDuration = $production->performanceDurationMinutes();
            $durationChanged = $newDuration !== $previousDuration;

            $previousCapacity = $production->capacity();
            $production->changeCapacity($command->capacity);
            $newCapacity = $production->capacity();
            $capacityChanged = $newCapacity !== null && $newCapacity !== $previousCapacity;

            $this->productions->save($production);

            if (! $capacityChanged && ! $durationChanged) {
                return $production;
            }

            foreach ($this->performances->findByProductionId($production->id()) as $listed) {
                // (R6) Lock order Production -> Performance -> Reservation.
                $performance = $this->performances->findByIdForUpdate($listed->id()) ?? $listed;

                if ($capacityChanged) {
                    // (R6) A Performance whose capacity would go down below the
                    // seats already taken refuses the whole update: nothing is
                    // saved (the transaction rolls back). A CANCELLED Performance
                    // takes no new bookings, so it is not checked.
                    if (
                        $newCapacity < $performance->capacity()
                        && ! $performance->status()->equals(PerformanceStatus::fromString(PerformanceStatus::CANCELLED))
                    ) {
                        $occupied = $this->occupiedSeats($performance);

                        if ($newCapacity < $occupied) {
                            throw new CapacityBelowReservationsException($performance->id()->toString(), $newCapacity, $occupied);
                        }
                    }

                    // §11/§26: unconditional overwrite of every child Performance's
                    // capacity, including individually-customized and CANCELLED ones.
                    $performance->changeCapacity($newCapacity);
                }

                if ($durationChanged && ! $performance->endTimeCustomized()) {
                    $performance->setAutoCalculatedEndTime(
                        $newDuration === null
                            ? null
                            : Performance::calculateEndTime($performance->startTime(), $newDuration)
                    );
                }

                $this->performances->save($performance);
            }

            return $production;
        });

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production),
            $this->authorization->activeDelegatesFor($person, $production),
            $this->authorization->canCompleteProduction($person, $production)
        );
    }

    private function parseOptionalDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid published_at value: {$value}");
        }
    }
}
