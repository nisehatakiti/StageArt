<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Presentation\Rest\ProductionRestController;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\ProductionStatusFixture;

/**
 * PR #7 R14-A (N1): the REST layer tells the Use Case which fields the
 * request sent, so the screens that send only some fields (publish toggle,
 * member management, the create screen's 「公開する」) no longer clear the
 * rest of the Production.
 */
final class ProductionRestControllerPartialUpdateTest extends TestCase
{
    private InMemoryProductionRepository $productions;
    private ProductionRestController $controller;
    private Production $production;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
        $GLOBALS['stageart_test_wp'] = ['current_user' => 1];

        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $authorization = new ProductionAuthorizationService(
            new OrganizationAuthorizationService($people, $memberships),
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );

        $organization = Organization::create(new OrganizationName('架空劇団'));
        $manager = Person::create(1);
        $people->save($manager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $manager->id()));
        $this->production = Production::create(Project::create($organization->id(), 'Season')->id(), new ProductionName('架空公演'), $manager->id());
        $this->production->changeSlug(new ProductionSlug('kaku-kouen'));
        $this->production->updateDescription('あらすじ', new DateTimeImmutable('2026-11-01T10:00:00+09:00'));
        $this->production->updateVenue('架空ホール', null);
        $this->production->updatePerformanceDurationMinutes(120);
        $this->production->changeCapacity(80);
        ProductionStatusFixture::force($this->production, 'ACTIVE');
        $this->productions->save($this->production);

        $this->controller = (new ReflectionClass(ProductionRestController::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(ProductionRestController::class, 'updateProduction');
        $property->setAccessible(true);
        $property->setValue($this->controller, new UpdateProductionUseCase(
            $this->productions,
            $authorization,
            new InMemoryPerformanceRepository(),
            new InMemoryTransactionManager(),
            new InMemoryReservationRepository()
        ));
    }

    private function put(array $body)
    {
        return $this->controller->update(new \WP_REST_Request(['id' => $this->production->id()->toString()] + $body));
    }

    public function test_the_publish_toggle_body_keeps_the_other_fields(): void
    {
        $response = $this->put(['name' => '架空公演', 'title_heading' => null, 'published' => true]);

        $this->assertSame(200, $response->get_status(), json_encode($response->get_data()));
        $saved = $this->productions->findById($this->production->id());
        $this->assertTrue($saved->isPublished());
        $this->assertSame(['あらすじ', '架空ホール', 120, 80], [$saved->description(), $saved->venueName(), $saved->performanceDurationMinutes(), $saved->capacity()]);
    }

    public function test_a_field_sent_as_null_is_cleared(): void
    {
        $response = $this->put(['name' => '架空公演', 'venue_name' => null]);

        $this->assertSame(200, $response->get_status(), json_encode($response->get_data()));
        $saved = $this->productions->findById($this->production->id());
        $this->assertNull($saved->venueName());
        $this->assertSame('あらすじ', $saved->description());
        $this->assertSame(80, $saved->capacity());
    }

    public function test_a_body_without_a_name_keeps_the_name(): void
    {
        $response = $this->put(['venue_name' => '新ホール']);

        $this->assertSame(200, $response->get_status(), json_encode($response->get_data()));
        $saved = $this->productions->findById($this->production->id());
        $this->assertSame(['架空公演', '新ホール'], [$saved->name()->toString(), $saved->venueName()]);
    }
}
