<?php

declare(strict_types=1);

namespace StageArt\Tests\Core\Adapter;

use PHPUnit\Framework\TestCase;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\ProjectId;
use StageArt\Tests\Support\InMemoryProductionRepository;

final class CoreProductionContextAdapterTest extends TestCase
{
    public function test_returns_a_summary_for_an_existing_production(): void
    {
        $productions = new InMemoryProductionRepository();
        $production = Production::create(ProjectId::generate(), new ProductionName('Autumn Show'), PersonId::generate());
        $productions->save($production);

        $adapter = new CoreProductionContextAdapter($productions);

        $summary = $adapter->getProduction($production->id());

        $this->assertNotNull($summary);
        $this->assertSame('Autumn Show', $summary->name);
        $this->assertTrue($summary->id->equals($production->id()));
        $this->assertSame('DRAFT', $summary->status);
    }

    public function test_returns_null_for_a_nonexistent_production(): void
    {
        $adapter = new CoreProductionContextAdapter(new InMemoryProductionRepository());

        $this->assertNull($adapter->getProduction(ProductionId::generate()));
    }
}
