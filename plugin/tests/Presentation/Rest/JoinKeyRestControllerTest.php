<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use PHPUnit\Framework\TestCase;
use StageArt\Application\JoinKey\DisableJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueProductionJoinKeyUseCase;
use StageArt\Application\JoinKey\ListOrganizationJoinKeysUseCase;
use StageArt\Application\JoinKey\ListProductionJoinKeysUseCase;
use StageArt\Application\JoinKey\ResolveJoinKeyUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\JoinKey\JoinKeyId;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Presentation\Rest\JoinKeyRestController;
use StageArt\Presentation\Rest\RestFailureResponder;
use StageArt\Tests\Support\InMemoryJoinKeyRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;

/**
 * PR #9 review No.1 / No.3: the Join Key REST routes answer the same way on
 * listing, issuing and disabling - an id that is not a UUID is 422 (never
 * a 500), an unknown target is 404, an existing one the caller may not
 * manage is 403. Called through RestFailureResponder::wrap(), as
 * Plugin.php registers them, so an uncaught exception would show as 500.
 *
 * WordPress users: 1 Organization Owner, 2 ORGANIZATION_MANAGER,
 * 10 PrimaryManager of the Production, 20 公演代理人, 21 会計担当, 30 outsider.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class JoinKeyRestControllerTest extends TestCase
{
    private JoinKeyRestController $controller;
    private string $organizationId;
    private string $productionId;
    private string $productionKeyId;
    private string $organizationKeyId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';

        $organizations = new InMemoryOrganizationRepository();
        $joinKeys = new InMemoryJoinKeyRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $organizationAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $delegates, new InMemoryParticipantRepository());

        $person = [];
        foreach ([1, 2, 10, 20, 21, 30] as $user) {
            $person[$user] = Person::create($user);
            $people->save($person[$user]);
        }

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);
        $this->organizationId = $organization->id()->toString();
        $memberships->save(Membership::createOwnerMembership($organization->id(), $person[1]->id()));
        $manager = Membership::createOwnerMembership($organization->id(), $person[2]->id());
        $manager->changeRole(RoleKey::organizationManager());
        $memberships->save($manager);

        $production = Production::create(Project::create($organization->id(), 'Season')->id(), new ProductionName('Show'), $person[10]->id());
        $productions->save($production);
        $this->productionId = $production->id()->toString();
        foreach ([[20, RoleKey::participantManager()], [20, RoleKey::rehearsalManager()], [21, RoleKey::accountingManager()]] as [$user, $role]) {
            $delegates->save(ProductionDelegate::create($production->id(), $person[$user]->id(), $role, $person[10]->id()));
        }

        $this->controller = new JoinKeyRestController(
            new IssueOrganizationJoinKeyUseCase($organizations, $joinKeys, $organizationAuthorization),
            new IssueProductionJoinKeyUseCase($productions, $joinKeys, $productionAuthorization),
            new ResolveJoinKeyUseCase($joinKeys, $organizations, $productions),
            new DisableJoinKeyUseCase($joinKeys, $productions, $organizationAuthorization, $productionAuthorization),
            new ListOrganizationJoinKeysUseCase($organizations, $joinKeys, $organizationAuthorization),
            new ListProductionJoinKeysUseCase($productions, $joinKeys, $productionAuthorization)
        );

        [$status, , $body] = $this->call(10, 'issueForProduction', $this->productionId);
        $this->assertSame(201, $status);
        $this->productionKeyId = $body['id'];
        [$status, , $body] = $this->call(1, 'issueForOrganization', $this->organizationId);
        $this->assertSame(201, $status);
        $this->organizationKeyId = $body['id'];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everyRoute(): array
    {
        return [
            'list organization keys' => ['listForOrganization'],
            'issue an organization key' => ['issueForOrganization'],
            'list production keys' => ['listForProduction'],
            'issue a production key' => ['issueForProduction'],
            'disable a key' => ['disable'],
        ];
    }

    /**
     * @dataProvider everyRoute
     */
    public function test_an_id_that_is_not_a_uuid_is_422_on_every_route(string $route): void
    {
        foreach ([1, 10, 30] as $user) {
            [$status, $code] = $this->call($user, $route, 'not-a-uuid');

            $this->assertSame(422, $status, "user {$user}");
            $this->assertSame($route === 'disable' ? 'stageart_join_key_id_invalid' : 'stageart_join_key_target_invalid', $code);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unknownTargets(): array
    {
        return [
            'list organization keys' => ['listForOrganization', 'stageart_organization_not_found'],
            'issue an organization key' => ['issueForOrganization', 'stageart_organization_not_found'],
            'list production keys' => ['listForProduction', 'stageart_production_not_found'],
            'issue a production key' => ['issueForProduction', 'stageart_production_not_found'],
            'disable a key' => ['disable', 'stageart_join_key_not_found'],
        ];
    }

    /**
     * @dataProvider unknownTargets
     */
    public function test_an_unknown_target_is_404_on_every_route(string $route, string $expectedCode): void
    {
        $unknown = [
            'listForOrganization' => OrganizationId::generate()->toString(),
            'issueForOrganization' => OrganizationId::generate()->toString(),
            'listForProduction' => ProductionId::generate()->toString(),
            'issueForProduction' => ProductionId::generate()->toString(),
            'disable' => JoinKeyId::generate()->toString(),
        ][$route];

        foreach ([1, 10, 30] as $user) {
            $this->assertSame([404, $expectedCode], array_slice($this->call($user, $route, $unknown), 0, 2), "user {$user}");
        }
    }

    public function test_an_existing_target_the_caller_may_not_manage_is_403(): void
    {
        foreach ([2, 10, 20, 21, 30] as $user) {
            $this->assertSame(403, $this->call($user, 'listForOrganization', $this->organizationId)[0], "user {$user} lists organization keys");
            $this->assertSame(403, $this->call($user, 'issueForOrganization', $this->organizationId)[0], "user {$user} issues an organization key");
            $this->assertSame(403, $this->call($user, 'disable', $this->organizationKeyId)[0], "user {$user} disables an organization key");
        }

        foreach ([1, 2, 21, 30] as $user) {
            $this->assertSame(403, $this->call($user, 'listForProduction', $this->productionId)[0], "user {$user} lists production keys");
            $this->assertSame(403, $this->call($user, 'issueForProduction', $this->productionId)[0], "user {$user} issues a production key");
            $this->assertSame(403, $this->call($user, 'disable', $this->productionKeyId)[0], "user {$user} disables a production key");
        }
    }

    public function test_the_permitted_callers_still_succeed(): void
    {
        $this->assertSame(200, $this->call(1, 'listForOrganization', $this->organizationId)[0]);
        $this->assertSame(200, $this->call(1, 'disable', $this->organizationKeyId)[0]);

        foreach ([10, 20] as $user) {
            $this->assertSame(200, $this->call($user, 'listForProduction', $this->productionId)[0], "user {$user}");
            $this->assertSame(201, $this->call($user, 'issueForProduction', $this->productionId)[0], "user {$user}");
        }

        $this->assertSame(200, $this->call(20, 'disable', $this->productionKeyId)[0]);
    }

    /**
     * @return array{int, string, array<string, mixed>}
     */
    private function call(int $wordPressUserId, string $method, string $id): array
    {
        $GLOBALS['stageart_test_wp']['current_user'] = $wordPressUserId;
        $response = RestFailureResponder::wrap([$this->controller, $method])(new \WP_REST_Request(['id' => $id]));

        if ($response instanceof \WP_Error) {
            return [(int) $response->get_error_data()['status'], $response->get_error_code(), []];
        }

        return [$response->get_status(), '', $response->get_data()];
    }
}
