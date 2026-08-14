<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Membership;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

final class MembershipTest extends TestCase
{
    public function test_membership_identity_is_independent_of_person_and_organization(): void
    {
        $organizationId = OrganizationId::generate();
        $personId = PersonId::generate();

        $first = Membership::create($organizationId, $personId, RoleKey::owner());
        $second = Membership::create($organizationId, $personId, RoleKey::owner());

        $this->assertFalse($first->id()->equals($second->id()));
    }

    public function test_membership_is_active_by_default(): void
    {
        $membership = Membership::create(OrganizationId::generate(), PersonId::generate(), RoleKey::member());

        $this->assertTrue($membership->isActive());
        $this->assertSame(RoleKey::MEMBER, $membership->roleKey()->toString());
    }
}
