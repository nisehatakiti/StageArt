<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Throwable;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\PerformanceFinishedListenerContract;
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
 *
 * アンケート実装指示書 §20/§42: `$performanceFinishedListener` is the one
 * generic hook fired after a Status transition lands on FINISHED - see
 * PerformanceFinishedListenerContract's own docblock for why this Module
 * still does not know Questionnaire exists. Optional/nullable so every
 * pre-existing caller (and every other Module's tests) keeps working
 * unchanged; passing null simply means nothing is notified.
 */
final class UpdatePerformanceUseCase
{
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private ?PerformanceFinishedListenerContract $performanceFinishedListener;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        ?PerformanceFinishedListenerContract $performanceFinishedListener = null
    ) {
        $this->performances = $performances;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->performanceFinishedListener = $performanceFinishedListener;
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

        $wasFinished = $performance->status()->equals(PerformanceStatus::fromString(PerformanceStatus::FINISHED));

        $performance->updateBasicInfo(
            $this->parseDate($command->performanceDate),
            $command->startTime,
            $command->endTime,
            $command->capacity,
            $command->remarks,
            $command->symbol
        );

        $duplicate = $this->performances->findByProductionAndDateTime(
            $productionId,
            $performance->performanceDate(),
            $performance->startTime(),
            $performance->id()
        );

        if ($duplicate !== null) {
            throw new PerformanceDuplicateDateTimeException($performance->performanceDate()->format('Y-m-d'), $performance->startTime());
        }

        if ($command->status !== null) {
            $performance->changeStatus(PerformanceStatus::fromString($command->status));
        }

        $this->performances->save($performance);

        $isNowFinished = $performance->status()->equals(PerformanceStatus::fromString(PerformanceStatus::FINISHED));

        if (! $wasFinished && $isNowFinished && $this->performanceFinishedListener !== null) {
            try {
                $this->performanceFinishedListener->onPerformanceFinished($performance->id());
            } catch (Throwable $exception) {
                // アンケート実装指示書 §42/§51: a listener failure (e.g. an
                // invite-Email problem) must never turn this into a failed
                // Performance update - the Performance Status change above
                // is already saved either way.
            }
        }

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
