<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Ticket\SalesEndRule;

/**
 * Chapter 32 §3.2's チケット設定 screen's "公開設定"/"販売設定" sections -
 * one Production-wide save, matching UpdateProductionUseCase's own
 * "whole form every time" convention.
 *
 * Depends directly on `Domain\Production\ProductionRepositoryInterface`
 * (a Domain-layer interface, not any Core Application internals) -
 * mirroring the exact reverse-direction precedent already established by
 * Phase 2's `UpdateProductionUseCase` depending directly on
 * `Domain\Performance\PerformanceRepositoryInterface`. Authorization
 * still goes through the Core Contract (`AuthorizationContract`), not
 * `ProductionAuthorizationService` directly, keeping this Module's own
 * access-control path consistent with every other Ticket UseCase.
 */
final class UpdateTicketSalesSettingsUseCase
{
    private ProductionRepositoryInterface $productions;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(ProductionRepositoryInterface $productions, IdentityContract $identity, AuthorizationContract $authorization)
    {
        $this->productions = $productions;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(UpdateTicketSalesSettingsCommand $command): TicketSalesSettingsResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TicketAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productions->findById($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, TicketCapability::MANAGE)) {
            throw new TicketAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the TICKET_MANAGER Role can update Ticket sales settings.'
            );
        }

        if ($command->salesEndRule !== null && $command->salesEndParameter !== null) {
            // Validated here purely to fail fast with a clear message;
            // Production itself stores the pair as opaque strings (see
            // Production::updateTicketSalesEndRule()'s own docblock).
            SalesEndRule::fromStored($command->salesEndRule, $command->salesEndParameter);
        } elseif ($command->salesEndRule !== null xor $command->salesEndParameter !== null) {
            throw new InvalidArgumentException('salesEndRule and salesEndParameter must both be set or both be null.');
        }

        $production->updateTicketPublicationAt($this->parseOptionalDateTime($command->publicationAt));
        $production->updateTicketSalesStartAt($this->parseOptionalDateTime($command->salesStartAt));
        $production->updateTicketSalesEndRule($command->salesEndRule, $command->salesEndParameter);

        $this->productions->save($production);

        return TicketSalesSettingsResult::fromDomain($production);
    }

    private function parseOptionalDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid date/time value: {$value}");
        }
    }
}
