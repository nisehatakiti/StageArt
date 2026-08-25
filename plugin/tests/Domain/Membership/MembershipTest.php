<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Membership;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Role\RoleKey;
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

    public function test_create_owner_membership_produces_an_owner_role(): void
    {
        $membership = Membership::createOwnerMembership(OrganizationId::generate(), PersonId::generate());

        $this->assertSame(RoleKey::OWNER, $membership->roleKey()->toString());
        $this->assertTrue($membership->isActive());
    }

    public function test_change_role_updates_the_role_key(): void
    {
        $membership = Membership::createOwnerMembership(OrganizationId::generate(), PersonId::generate());

        $membership->changeRole(RoleKey::member());

        $this->assertSame(RoleKey::MEMBER, $membership->roleKey()->toString());

        $membership->changeRole(RoleKey::owner());

        $this->assertSame(RoleKey::OWNER, $membership->roleKey()->toString());
    }

    public function test_request_membership_starts_requested_with_no_joined_at(): void
    {
        $membership = Membership::requestMembership(OrganizationId::generate(), PersonId::generate());

        $this->assertTrue($membership->isPending());
        $this->assertSame(Membership::STATUS_REQUESTED, $membership->status());
        $this->assertSame(RoleKey::MEMBER, $membership->roleKey()->toString());
        $this->assertNull($membership->joinedAt());
    }

    public function test_approving_a_requested_membership_activates_it_and_records_joined_at(): void
    {
        $membership = Membership::requestMembership(OrganizationId::generate(), PersonId::generate());

        $membership->approve();

        $this->assertSame(Membership::STATUS_ACTIVE, $membership->status());
        $this->assertFalse($membership->isPending());
        $this->assertNotNull($membership->joinedAt());
    }

    public function test_rejecting_a_requested_membership_marks_it_rejected(): void
    {
        $membership = Membership::requestMembership(OrganizationId::generate(), PersonId::generate());

        $membership->reject();

        $this->assertSame(Membership::STATUS_REJECTED, $membership->status());
        $this->assertNull($membership->joinedAt());
    }

    public function test_an_already_active_membership_cannot_be_approved_again(): void
    {
        $membership = Membership::createOwnerMembership(OrganizationId::generate(), PersonId::generate());

        $this->expectException(\InvalidArgumentException::class);

        $membership->approve();
    }
}
