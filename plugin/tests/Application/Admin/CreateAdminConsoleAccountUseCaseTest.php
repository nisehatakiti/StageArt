<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Admin\AdminConsoleAccountCreationException;
use StageArt\Application\Admin\CreateAdminConsoleAccountCommand;
use StageArt\Application\Admin\CreateAdminConsoleAccountUseCase;
use StageArt\Tests\Support\FakeAdminConsoleAccountProvisioner;

final class CreateAdminConsoleAccountUseCaseTest extends TestCase
{
    private FakeAdminConsoleAccountProvisioner $provisioner;
    private CreateAdminConsoleAccountUseCase $createAdminConsoleAccount;

    protected function setUp(): void
    {
        $this->provisioner = new FakeAdminConsoleAccountProvisioner();
        $this->createAdminConsoleAccount = new CreateAdminConsoleAccountUseCase($this->provisioner);
    }

    public function test_creates_an_admin_console_account_with_valid_input(): void
    {
        $this->createAdminConsoleAccount->execute(
            new CreateAdminConsoleAccountCommand('ops_admin', 'ops@example.com', 'password123')
        );

        $this->assertCount(1, $this->provisioner->provisioned);
        $this->assertSame('ops_admin', $this->provisioner->provisioned[0]['username']);
        $this->assertSame('ops@example.com', $this->provisioner->provisioned[0]['email']);
    }

    public function test_rejects_an_empty_username(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createAdminConsoleAccount->execute(new CreateAdminConsoleAccountCommand('', 'ops@example.com', 'password123'));
    }

    public function test_rejects_an_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createAdminConsoleAccount->execute(new CreateAdminConsoleAccountCommand('ops_admin', 'not-an-email', 'password123'));
    }

    public function test_rejects_a_too_short_password(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createAdminConsoleAccount->execute(new CreateAdminConsoleAccountCommand('ops_admin', 'ops@example.com', 'short'));
    }

    public function test_a_duplicate_username_surfaces_the_provisioners_error(): void
    {
        $this->provisioner->rejectUsername('ops_admin');

        $this->expectException(AdminConsoleAccountCreationException::class);
        $this->createAdminConsoleAccount->execute(
            new CreateAdminConsoleAccountCommand('ops_admin', 'ops@example.com', 'password123')
        );
    }
}
