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
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionStatus;

/**
 * StageArt Core/Module Architecture: depends only on Core Contracts, not
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`
 * directly - matching Rehearsal's own Phase 3 wiring.
 *
 * Phase 2 Performance基盤 instruction §8: a COMPLETED or ARCHIVED
 * Production blocks new Performance creation. §10: a null `capacity` on
 * the Command inherits the parent Production's own `capacity`; the
 * Production itself must have a capacity set, or creation fails (a
 * Performance's capacity is a required field - §6).
 */
final class CreatePerformanceUseCase
{
    private ProductionContextContract $productionContext;
    private PerformanceRepositoryInterface $performances;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ProductionContextContract $productionContext,
        PerformanceRepositoryInterface $performances,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->productionContext = $productionContext;
        $this->performances = $performances;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(CreatePerformanceCommand $command): PerformanceResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new PerformanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, PerformanceCapability::CREATE)) {
            throw new PerformanceAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PERFORMANCE_MANAGER Role can create Performances.'
            );
        }

        if (in_array($production->status, [ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED], true)) {
            throw new InvalidArgumentException(
                'Performances cannot be created for a COMPLETED or ARCHIVED Production.'
            );
        }

        $capacity = $command->capacity ?? $production->capacity;

        if ($capacity === null) {
            throw new InvalidArgumentException(
                'Performance capacity is required and this Production has no capacity set to inherit from.'
            );
        }

        $performance = Performance::create(
            $productionId,
            $this->parseDate($command->performanceDate),
            $command->startTime,
            $command->endTime,
            $capacity,
            $command->remarks,
            $command->symbol
        );

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
