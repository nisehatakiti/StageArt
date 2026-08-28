<?php

declare(strict_types=1);

namespace StageArt\Tests\Core;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\AccountingModuleDescriptor;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Core\Module\ModuleRegistry;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Rehearsal\RehearsalModuleDescriptor;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

/**
 * StageArt Core/Module Architecture Phase 3 (docs/architecture/
 * WordPressPluginModuleBoundary.md §11): "Coreだけでも動作する" made
 * concrete - Core's own Contract Adapters are constructed and exercised
 * here with `RehearsalModuleBootstrap` never even imported, let alone
 * constructed. If Core secretly required Rehearsal to be present, this
 * test would fail to even compile (a missing `use` of a Rehearsal class
 * would be needed) or would throw at runtime; neither happens.
 *
 * `ModuleRegistry` is checked as empty throughout - the concrete,
 * testable form of "no Module is required for Core to boot".
 */
final class CoreOnlyBootstrapTest extends TestCase
{
    public function test_core_contract_adapters_work_with_no_module_registered(): void
    {
        $registry = new ModuleRegistry();

        $this->assertSame([], $registry->all());
        $this->assertFalse($registry->isRegistered('rehearsal'));
        $this->assertFalse($registry->isRegistered('accounting'));

        $people = new InMemoryPersonRepository();
        $person = Person::create(1);
        $people->save($person);

        $identity = new CoreIdentityAdapter($people);
        $this->assertTrue($identity->resolveCurrentPersonId(1)->equals($person->id()));
        $this->assertNull($identity->resolveCurrentPersonId(999));

        $projects = new InMemoryProjectRepository();
        $productions = new InMemoryProductionRepository();
        $project = Project::create(OrganizationId::generate(), 'Season');
        $projects->save($project);
        $production = Production::create($project->id(), new ProductionName('Show'), $person->id());
        $productions->save($production);

        $productionContext = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver($projects));
        $summary = $productionContext->getProduction($production->id());

        $this->assertNotNull($summary);
        $this->assertSame('Show', $summary->name);

        // Still no Module registered - Core's own Contracts do not
        // require one to function.
        $this->assertSame([], $registry->all());
    }

    public function test_registering_a_module_descriptor_does_not_require_its_bootstrap_to_run(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new RehearsalModuleDescriptor());
        $registry->register(new AccountingModuleDescriptor());

        $this->assertTrue($registry->isRegistered('rehearsal'));
        $this->assertTrue($registry->isRegistered('accounting'));
        $this->assertCount(2, $registry->all());

        foreach (['rehearsal', 'accounting'] as $moduleId) {
            $descriptor = $registry->get($moduleId);

            $this->assertNotNull($descriptor);
            $this->assertSame($moduleId, $descriptor->moduleId());
            $this->assertNotEmpty($descriptor->requiredContracts());
            $this->assertNotEmpty($descriptor->ownedTables());
        }

        // Registering both descriptors is pure metadata - it does not
        // construct RehearsalModuleBootstrap/AccountingModuleBootstrap
        // or touch any repository. Nothing here has instantiated a
        // WordPress Infrastructure class.
    }
}
