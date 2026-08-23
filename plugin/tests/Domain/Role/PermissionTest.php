<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Role;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Role\Permission;

final class PermissionTest extends TestCase
{
    public function test_accepts_resource_dot_action_format(): void
    {
        $permission = Permission::fromString('Production.Read');

        $this->assertSame('Production.Read', $permission->toString());
    }

    public function test_rejects_a_value_without_a_dot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Permission::fromString('ProductionRead');
    }

    public function test_equality_is_by_value(): void
    {
        $this->assertTrue(Permission::fromString('Rehearsal.Update')->equals(Permission::fromString('Rehearsal.Update')));
        $this->assertFalse(Permission::fromString('Rehearsal.Update')->equals(Permission::fromString('Rehearsal.Delete')));
    }
}
