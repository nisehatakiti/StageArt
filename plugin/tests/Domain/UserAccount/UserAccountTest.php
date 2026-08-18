<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\UserAccount;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountStatus;

final class UserAccountTest extends TestCase
{
    public function test_create_assigns_a_unique_id_and_active_status(): void
    {
        $personId = PersonId::generate();
        $userAccount = UserAccount::create($personId);

        $this->assertTrue($userAccount->personId()->equals($personId));
        $this->assertSame(UserAccountStatus::ACTIVE, $userAccount->status()->toString());
        $this->assertTrue($userAccount->isActive());
        $this->assertNotEmpty($userAccount->id()->toString());
    }

    public function test_two_user_accounts_for_different_people_have_different_ids(): void
    {
        $first = UserAccount::create(PersonId::generate());
        $second = UserAccount::create(PersonId::generate());

        $this->assertFalse($first->id()->equals($second->id()));
    }

    public function test_suspend_and_disable_transitions(): void
    {
        $userAccount = UserAccount::create(PersonId::generate());

        $userAccount->suspend();
        $this->assertSame(UserAccountStatus::SUSPENDED, $userAccount->status()->toString());
        $this->assertFalse($userAccount->isActive());

        $userAccount->activate();
        $this->assertTrue($userAccount->isActive());

        $userAccount->disable();
        $this->assertSame(UserAccountStatus::DISABLED, $userAccount->status()->toString());
        $this->assertFalse($userAccount->isActive());
    }
}
