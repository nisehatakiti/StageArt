<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Performance\PerformanceStatus;
use StageArt\Domain\Production\ProductionStatus;

/**
 * StageArt Core/Module Architecture: depends only on Core Contracts.
 *
 * Phase 2 Performance基盤 instruction §8: a COMPLETED or ARCHIVED
 * Production blocks Performance edits, same guard as creation.
 */
final class UpdatePerformanceUseCase
{
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->performances = $performances;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(UpdatePerformanceCommand $command): PerformanceResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new PerformanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $performance = $this->performances->findById(PerformanceId::fromString($command->performanceId));

        if (! $performance) {
            throw new PerformanceNotFoundException($command->performanceId);
        }

        $productionId = $performance->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, PerformanceCapability::UPDATE)) {
            throw new PerformanceAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PERFORMANCE_MANAGER Role can update this Performance.'
            );
        }

        if (in_array($production->status, [ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED], true)) {
            throw new InvalidArgumentException(
                'Performances cannot be edited while their Production is COMPLETED or ARCHIVED.'
            );
        }

        $performance->updateBasicInfo(
            $this->parseDate($command->performanceDate),
            $command->startTime,
            $command->endTime,
            $command->capacity,
            $command->remarks,
            $command->symbol
        );

        if ($command->status !== null) {
            $performance->changeStatus(PerformanceStatus::fromString($command->status));
        }

        $this->performances->save($performance);

        return PerformanceResult::fromDomain($performance);
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid performance_date value: {$value}");
        }
    }
}
