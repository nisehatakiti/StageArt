<?php

declare(strict_types=1);

namespace StageArt\Application\Admin;

use InvalidArgumentException;

/**
 * StageArt Admin Console V1: creates a standalone WordPress User
 * carrying only the 'stageart_admin' Role/'stageart_manage_accounts'
 * Capability - deliberately NOT a StageArt Person or UserAccount (the
 * instruction's explicit "StageArt一般ユーザーアカウントとは別途定義
 * する...同一概念として扱わない"). This account never appears in
 * ListAllUserAccountsUseCase's results, since that Use Case only ever
 * reads from the stageart_user_accounts table, which this path never
 * writes to.
 */
final class CreateAdminConsoleAccountUseCase
{
    private const MIN_PASSWORD_LENGTH = 8;

    private AdminConsoleAccountProvisionerInterface $provisioner;

    public function __construct(AdminConsoleAccountProvisionerInterface $provisioner)
    {
        $this->provisioner = $provisioner;
    }

    public function execute(CreateAdminConsoleAccountCommand $command): void
    {
        if (trim($command->username) === '') {
            throw new InvalidArgumentException('A username is required.');
        }

        if (! filter_var($command->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        if (mb_strlen($command->password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
        }

        $this->provisioner->provision($command->username, $command->email, $command->password);
    }
}
