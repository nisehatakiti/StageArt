<?php

declare(strict_types=1);

namespace StageArt\Domain\UserAccount;

use StageArt\Domain\Person\PersonId;

interface UserAccountRepositoryInterface
{
    public function save(UserAccount $userAccount): void;

    public function findById(UserAccountId $id): ?UserAccount;

    public function findByPersonId(PersonId $personId): ?UserAccount;

    /**
     * StageArt Admin Console V1: platform-wide listing for the
     * Account Management screen - every other query on this interface
     * is scoped to a single caller's own Person, this is the one
     * deliberate exception (only reachable through an Admin Console
     * Use Case, gated by the `stageart_manage_accounts` WordPress
     * Capability, never through any user-facing REST route).
     *
     * @return UserAccount[]
     */
    public function findAll(): array;
}
