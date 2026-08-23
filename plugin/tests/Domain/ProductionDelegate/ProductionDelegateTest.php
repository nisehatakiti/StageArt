<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\ProductionDelegate;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;

final class ProductionDelegateTest extends TestCase
{
    public function test_create_starts_active_with_updated_by_equal_to_created_by(): void
    {
        $creator = PersonId::generate();

        $delegate = ProductionDelegate::create(
            ProductionId::generate(),
            PersonId::generate(),
            RoleKey::participantManager(),
            $creator
        );

        $this->assertTrue($delegate->isActive());
        $this->assertTrue($delegate->createdBy()->equals($creator));
        $this->assertTrue($delegate->updatedBy()->equals($creator));
        $this->assertSame($delegate->createdAt(), $delegate->updatedAt());
    }

    public function test_deactivate_and_activate_toggle_status(): void
    {
        $delegate = ProductionDelegate::create(
            ProductionId::generate(),
            PersonId::generate(),
            RoleKey::participantManager(),
            PersonId::generate()
        );

        $updater = PersonId::generate();
        $delegate->deactivate($updater);

        $this->assertFalse($delegate->isActive());
        $this->assertTrue($delegate->updatedBy()->equals($updater));

        $delegate->activate($updater);
        $this->assertTrue($delegate->isActive());
    }

    public function test_change_role_replaces_the_role(): void
    {
        $delegate = ProductionDelegate::create(
            ProductionId::generate(),
            PersonId::generate(),
            RoleKey::participantManager(),
            PersonId::generate()
        );

        $delegate->changeRole(RoleKey::fromString(RoleKey::PARTICIPANT_MANAGER), PersonId::generate());

        $this->assertSame(RoleKey::PARTICIPANT_MANAGER, $delegate->role()->toString());
    }
}
