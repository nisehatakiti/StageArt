<?php

declare(strict_types=1);

namespace StageArt\Domain\Account;

use StageArt\Domain\Organization\OrganizationId;

interface AccountRepositoryInterface
{
    public function save(Account $account): void;

    public function findById(AccountId $id): ?Account;

    /**
     * @return Account[]
     */
    public function findByIds(array $ids): array;

    /**
     * @return Account[]
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;
}
