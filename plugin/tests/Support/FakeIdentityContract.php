<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Person\PersonId;

/**
 * A hand-written test double for IdentityContract - maps WordPress user
 * ids to PersonIds directly, with no `PersonRepositoryInterface`/Core
 * Infrastructure involved at all. See
 * tests/Application/Rehearsal/RehearsalModuleContractIsolationTest.php.
 */
final class FakeIdentityContract implements IdentityContract
{
    /** @var array<int, PersonId> */
    private array $personIdsByWordPressUserId = [];

    public function register(int $wordPressUserId, PersonId $personId): void
    {
        $this->personIdsByWordPressUserId[$wordPressUserId] = $personId;
    }

    public function resolveCurrentPersonId(int $wordPressUserId): ?PersonId
    {
        return $this->personIdsByWordPressUserId[$wordPressUserId] ?? null;
    }
}
