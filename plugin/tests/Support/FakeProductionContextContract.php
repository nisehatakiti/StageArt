<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Contract\ProductionPublicSlugs;
use StageArt\Core\Contract\ProductionSummary;
use StageArt\Core\Contract\ProductionTicketSettings;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Production\ProductionId;

/**
 * A hand-written test double for ProductionContextContract - holds
 * plain ProductionSummary values, no `ProductionRepositoryInterface`/
 * Core Infrastructure involved at all. See
 * tests/Application/Rehearsal/RehearsalModuleContractIsolationTest.php.
 */
final class FakeProductionContextContract implements ProductionContextContract
{
    /** @var array<string, ProductionSummary> */
    private array $productions = [];

    /** @var array<string, OrganizationId> */
    private array $organizationIds = [];

    /** @var array<string, ProductionTicketSettings> */
    private array $ticketSettings = [];

    /** @var array<string, ProductionPublicSlugs> */
    private array $publicSlugs = [];

    public function register(ProductionId $id, string $name, string $status = 'DRAFT', ?OrganizationId $organizationId = null): void
    {
        $this->productions[$id->toString()] = new ProductionSummary($id, $name, $status);

        if ($organizationId !== null) {
            $this->organizationIds[$id->toString()] = $organizationId;
        }
    }

    public function getProduction(ProductionId $productionId): ?ProductionSummary
    {
        return $this->productions[$productionId->toString()] ?? null;
    }

    public function getProductions(array $productionIds): array
    {
        $byId = [];

        foreach ($productionIds as $productionId) {
            $summary = $this->productions[$productionId->toString()] ?? null;

            if ($summary !== null) {
                $byId[$productionId->toString()] = $summary;
            }
        }

        return $byId;
    }

    public function getProductionOrganizationId(ProductionId $productionId): ?OrganizationId
    {
        return $this->organizationIds[$productionId->toString()] ?? null;
    }

    public function getProductionTicketSettings(ProductionId $productionId): ?ProductionTicketSettings
    {
        return $this->ticketSettings[$productionId->toString()] ?? null;
    }

    public function registerTicketSettings(ProductionId $productionId, ProductionTicketSettings $settings): void
    {
        $this->ticketSettings[$productionId->toString()] = $settings;
    }

    /**
     * Backend PHPUnit環境整備 Phase: ProductionContextContract gained
     * getProductionPublicSlugs() during the Questionnaire Phase - null
     * by default (matching CoreProductionContextAdapter's own "no slug
     * registered yet" behavior), only meaningful for a test that
     * explicitly calls registerPublicSlugs() first.
     */
    public function getProductionPublicSlugs(ProductionId $productionId): ?ProductionPublicSlugs
    {
        return $this->publicSlugs[$productionId->toString()] ?? null;
    }

    public function registerPublicSlugs(ProductionId $productionId, ProductionPublicSlugs $slugs): void
    {
        $this->publicSlugs[$productionId->toString()] = $slugs;
    }
}
