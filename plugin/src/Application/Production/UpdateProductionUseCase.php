<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
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

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        PerformanceRepositoryInterface $performances,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->performances = $performances;
        $this->transactions = $transactions;
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

        $previousCapacity = $production->capacity();
        $production->changeCapacity($command->capacity);
        $newCapacity = $production->capacity();
        $capacityChanged = $newCapacity !== null && $newCapacity !== $previousCapacity;

        $this->transactions->run(function () use ($production, $capacityChanged, $newCapacity): void {
            $this->productions->save($production);

            if (! $capacityChanged) {
                return;
            }

            // §11/§26: unconditional overwrite of every child Performance's
            // capacity, including individually-customized and CANCELLED
            // ones - no filtering by status here on purpose.
            foreach ($this->performances->findByProductionId($production->id()) as $performance) {
                $performance->changeCapacity($newCapacity);
                $this->performances->save($performance);
            }
        });

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production)
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
