<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Account;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountStatus;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;

final class AccountTest extends TestCase
{
    public function test_create_starts_active(): void
    {
        $account = Account::create(OrganizationId::generate(), 'チケット売上', AccountType::fromString(AccountType::REVENUE));

        $this->assertSame('チケット売上', $account->name());
        $this->assertSame(AccountType::REVENUE, $account->type()->toString());
        $this->assertSame(AccountStatus::ACTIVE, $account->status()->toString());
        $this->assertTrue($account->isActive());
    }

    public function test_create_rejects_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Account::create(OrganizationId::generate(), '  ', AccountType::fromString(AccountType::EXPENSE));
    }

    public function test_deactivate_and_activate_toggle_status(): void
    {
        $account = Account::create(OrganizationId::generate(), '会場費', AccountType::fromString(AccountType::EXPENSE));

        $account->deactivate();
        $this->assertFalse($account->isActive());

        $account->activate();
        $this->assertTrue($account->isActive());
    }

    public function test_account_type_normal_balance(): void
    {
        $this->assertTrue(AccountType::fromString(AccountType::ASSET)->isDebitNormal());
        $this->assertTrue(AccountType::fromString(AccountType::EXPENSE)->isDebitNormal());
        $this->assertFalse(AccountType::fromString(AccountType::LIABILITY)->isDebitNormal());
        $this->assertFalse(AccountType::fromString(AccountType::EQUITY)->isDebitNormal());
        $this->assertFalse(AccountType::fromString(AccountType::REVENUE)->isDebitNormal());
    }
}
