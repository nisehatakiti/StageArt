<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Production\ProductionSlug;

/**
 * Production information / public page updates (name, title heading,
 * slug, publish / unpublish) are allowed to the PrimaryManager and this
 * Production's 公演代理人 (ProductionAuthorizationService::
 * canActAsProductionManager()); 稽古担当, 会計担当 and other single-Role
 * delegates are refused. Only the fields of UpdateProductionCommand can
 * change here - never Status (Lifecycle, see below) nor the
 * PrimaryManager (ChangePrimaryManagerUseCase), which stay
 * PrimaryManager-exclusive.
 *
 * Phase 6.1: no longer touches Status. See UpdateProductionCommand's
 * docblock - Status changes go through the dedicated Lifecycle Action
 * UseCases instead.
 */
final class UpdateProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(ProductionRepositoryInterface $productions, ProductionAuthorizationService $authorization)
    {
        $this->productions = $productions;
        $this->authorization = $authorization;
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

        if (! $this->authorization->canActAsProductionManager($person, $production)) {
            throw new ProductionAccessDeniedException('Only the PrimaryManager or the Production proxy manager can update this Production.');
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

        $this->productions->save($production);

        return ProductionResult::fromDomain(
            $production,
            $this->authorization->isPrimaryManager($person, $production),
            $this->authorization->activeDelegateFor($person, $production),
            $this->authorization->activeDelegatesFor($person, $production)
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
