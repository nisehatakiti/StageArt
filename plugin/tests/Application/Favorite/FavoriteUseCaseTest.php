<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Favorite;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Favorite\AddFavoriteCommand;
use StageArt\Application\Favorite\AddFavoriteUseCase;
use StageArt\Application\Favorite\FavoriteTargetNotFoundException;
use StageArt\Application\Favorite\ListMyFavoritesQuery;
use StageArt\Application\Favorite\ListMyFavoritesUseCase;
use StageArt\Application\Favorite\RemoveFavoriteCommand;
use StageArt\Application\Favorite\RemoveFavoriteUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Favorite\Favorite;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryFavoriteRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class FavoriteUseCaseTest extends TestCase
{
    private function authorization(InMemoryPersonRepository $people): OrganizationAuthorizationService
    {
        return new OrganizationAuthorizationService($people, new InMemoryMembershipRepository());
    }

    public function test_adding_a_favorite_for_a_published_organization_succeeds_and_is_idempotent(): void
    {
        $favorites = new InMemoryFavoriteRepository();
        $organizations = new InMemoryOrganizationRepository();
        $productions = new InMemoryProductionRepository();
        $people = new InMemoryPersonRepository();
        $person = Person::create(1);
        $people->save($person);

        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $organization->publish();
        $organizations->save($organization);

        $useCase = new AddFavoriteUseCase($favorites, $organizations, $productions, $this->authorization($people));

        $useCase->execute(new AddFavoriteCommand(1, Favorite::TARGET_TYPE_ORGANIZATION, $organization->id()->toString()));
        $result = $useCase->execute(new AddFavoriteCommand(1, Favorite::TARGET_TYPE_ORGANIZATION, $organization->id()->toString()));

        $this->assertTrue($result->isFavorited);
        $this->assertCount(1, $favorites->findByPersonId($person->id()));
    }

    public function test_adding_a_favorite_for_an_unpublished_organization_is_not_found(): void
    {
        $favorites = new InMemoryFavoriteRepository();
        $organizations = new InMemoryOrganizationRepository();
        $productions = new InMemoryProductionRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $organization = Organization::create(new OrganizationName('Draft Co'));
        $organizations->save($organization);

        $useCase = new AddFavoriteUseCase($favorites, $organizations, $productions, $this->authorization($people));

        $this->expectException(FavoriteTargetNotFoundException::class);

        $useCase->execute(new AddFavoriteCommand(1, Favorite::TARGET_TYPE_ORGANIZATION, $organization->id()->toString()));
    }

    public function test_remove_is_idempotent_and_list_reflects_removal(): void
    {
        $favorites = new InMemoryFavoriteRepository();
        $organizations = new InMemoryOrganizationRepository();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $authorization = $this->authorization($people);

        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $organization->publish();
        $organizations->save($organization);

        $addUseCase = new AddFavoriteUseCase($favorites, $organizations, $productions, $authorization);
        $addUseCase->execute(new AddFavoriteCommand(1, Favorite::TARGET_TYPE_ORGANIZATION, $organization->id()->toString()));

        $listUseCase = new ListMyFavoritesUseCase($favorites, $organizations, $productions, $projects, $authorization);
        $this->assertCount(1, $listUseCase->execute(new ListMyFavoritesQuery(1)));

        $removeUseCase = new RemoveFavoriteUseCase($favorites, $authorization);
        $result = $removeUseCase->execute(new RemoveFavoriteCommand(1, Favorite::TARGET_TYPE_ORGANIZATION, $organization->id()->toString()));
        $this->assertFalse($result->isFavorited);

        $this->assertCount(0, $listUseCase->execute(new ListMyFavoritesQuery(1)));

        // Removing again is a no-op success, not an error.
        $removeUseCase->execute(new RemoveFavoriteCommand(1, Favorite::TARGET_TYPE_ORGANIZATION, $organization->id()->toString()));
    }

    public function test_list_includes_a_production_favorite_with_its_organization_slug(): void
    {
        $favorites = new InMemoryFavoriteRepository();
        $organizations = new InMemoryOrganizationRepository();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $authorization = $this->authorization($people);

        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $organization->publish();
        $organizations->save($organization);

        $project = Project::create($organization->id(), null);
        $projects->save($project);

        $production = Production::create(
            $project->id(),
            new ProductionName('Autumn Play'),
            Person::create(1)->id(),
            null,
            new ProductionSlug('autumn-play')
        );
        $production->publish();
        $productions->save($production);

        $addUseCase = new AddFavoriteUseCase($favorites, $organizations, $productions, $authorization);
        $addUseCase->execute(new AddFavoriteCommand(1, Favorite::TARGET_TYPE_PRODUCTION, $production->id()->toString()));

        $listUseCase = new ListMyFavoritesUseCase($favorites, $organizations, $productions, $projects, $authorization);
        $results = $listUseCase->execute(new ListMyFavoritesQuery(1));

        $this->assertCount(1, $results);
        $this->assertSame('Autumn Play', $results[0]->targetName);
        $this->assertSame('theatre-co', $results[0]->organizationSlug);
    }
}
