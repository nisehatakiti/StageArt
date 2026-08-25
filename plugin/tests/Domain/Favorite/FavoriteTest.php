<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Favorite;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Favorite\Favorite;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class FavoriteTest extends TestCase
{
    public function test_create_for_an_organization_target(): void
    {
        $organizationId = OrganizationId::generate();
        $favorite = Favorite::create(PersonId::generate(), Favorite::TARGET_TYPE_ORGANIZATION, $organizationId->toString());

        $this->assertSame(Favorite::TARGET_TYPE_ORGANIZATION, $favorite->targetType());
        $this->assertSame($organizationId->toString(), $favorite->targetId());
    }

    public function test_create_for_a_production_target(): void
    {
        $productionId = ProductionId::generate();
        $favorite = Favorite::create(PersonId::generate(), Favorite::TARGET_TYPE_PRODUCTION, $productionId->toString());

        $this->assertSame(Favorite::TARGET_TYPE_PRODUCTION, $favorite->targetType());
    }

    public function test_invalid_target_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Favorite::create(PersonId::generate(), 'SOMETHING_ELSE', 'x');
    }

    public function test_identity_is_independent_of_person_and_target(): void
    {
        $personId = PersonId::generate();
        $targetId = OrganizationId::generate()->toString();

        $first = Favorite::create($personId, Favorite::TARGET_TYPE_ORGANIZATION, $targetId);
        $second = Favorite::create($personId, Favorite::TARGET_TYPE_ORGANIZATION, $targetId);

        $this->assertFalse($first->id()->equals($second->id()));
    }
}
