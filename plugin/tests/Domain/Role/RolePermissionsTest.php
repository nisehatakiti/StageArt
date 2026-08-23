<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Role;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Role\Permission;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Role\RolePermissions;

final class RolePermissionsTest extends TestCase
{
    public function test_participant_manager_has_participant_permissions(): void
    {
        $role = RoleKey::participantManager();

        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Participant.Read')));
        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Participant.Create')));
        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Participant.Update')));
        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Participant.Delete')));
    }

    public function test_participant_manager_does_not_have_rehearsal_permissions(): void
    {
        $role = RoleKey::participantManager();

        $this->assertFalse(RolePermissions::hasPermission($role, Permission::fromString('Rehearsal.Update')));
    }

    public function test_rehearsal_manager_has_rehearsal_and_schedule_read_permissions(): void
    {
        $role = RoleKey::rehearsalManager();

        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Rehearsal.Read')));
        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Rehearsal.Create')));
        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Rehearsal.Update')));
        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Rehearsal.Delete')));
        $this->assertTrue(RolePermissions::hasPermission($role, Permission::fromString('Schedule.Read')));
    }

    public function test_rehearsal_manager_does_not_have_participant_permissions(): void
    {
        $role = RoleKey::rehearsalManager();

        $this->assertFalse(RolePermissions::hasPermission($role, Permission::fromString('Participant.Update')));
    }

    public function test_owner_and_member_have_no_entries_in_the_permission_set_registry(): void
    {
        // Organization Scope authorization (OrganizationAuthorizationService::hasRole())
        // continues to check membership in an explicit allowed-RoleKey list rather than
        // a Permission lookup - see RolePermissions' own class docblock for why.
        $this->assertFalse(RolePermissions::hasPermission(RoleKey::owner(), Permission::fromString('Participant.Update')));
        $this->assertFalse(RolePermissions::hasPermission(RoleKey::member(), Permission::fromString('Participant.Update')));
    }
}
