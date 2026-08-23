<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Role;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Role\RoleKey;

final class RoleKeyTest extends TestCase
{
    public function test_owner_and_member_are_valid_role_keys(): void
    {
        $this->assertSame('OWNER', RoleKey::owner()->toString());
        $this->assertSame('MEMBER', RoleKey::member()->toString());
    }

    public function test_participant_manager_and_rehearsal_manager_are_valid_role_keys(): void
    {
        $this->assertSame('PARTICIPANT_MANAGER', RoleKey::participantManager()->toString());
        $this->assertSame('REHEARSAL_MANAGER', RoleKey::rehearsalManager()->toString());
    }

    public function test_unknown_role_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RoleKey::fromString('NOT_A_REAL_ROLE');
    }

    public function test_same_role_key_type_is_used_regardless_of_scope(): void
    {
        // Role.md: "同じRole Definitionを、Organization ScopeとProduction
        // Scopeの両方で利用できる" - there is exactly one RoleKey class,
        // not a separate Organization-scope and Production-scope enum.
        $organizationScopeRole = RoleKey::owner();
        $productionScopeRole = RoleKey::participantManager();

        $this->assertInstanceOf(RoleKey::class, $organizationScopeRole);
        $this->assertInstanceOf(RoleKey::class, $productionScopeRole);
    }

    public function test_role_key_equality_is_by_value(): void
    {
        $this->assertTrue(RoleKey::owner()->equals(RoleKey::fromString('OWNER')));
        $this->assertFalse(RoleKey::owner()->equals(RoleKey::member()));
    }
}
