<?php

declare(strict_types=1);

namespace StageArt\Domain\UserAccount;

use StageArt\Domain\Person\PersonId;

interface UserAccountRepositoryInterface
{
    public function save(UserAccount $userAccount): void;

    public function findById(UserAccountId $id): ?UserAccount;

    public function findByPersonId(PersonId $personId): ?UserAccount;
}
