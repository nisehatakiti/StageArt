<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * A hand-written test double for AuthorizationContract - a simple
 * allow-list keyed by (personId, productionId, capability), no
 * `ProductionAuthorizationService`/Core Infrastructure involved at all.
 * See tests/Application/Rehearsal/RehearsalModuleContractIsolationTest.php.
 */
final class FakeAuthorizationContract implements AuthorizationContract
{
    /** @var array<int, PersonId> */
    private array $personIdsByWordPressUserId = [];

    /** @var array<string, true> */
    private array $grants = [];

    public function registerIdentity(int $wordPressUserId, PersonId $personId): void
    {
        $this->personIdsByWordPressUserId[$wordPressUserId] = $personId;
    }

    public function grant(PersonId $personId, ProductionId $productionId, string $capability): void
    {
        $this->grants[$this->key($personId, $productionId, $capability)] = true;
    }

    public function resolveCurrentPersonId(int $wordPressUserId): ?PersonId
    {
        return $this->personIdsByWordPressUserId[$wordPressUserId] ?? null;
    }

    public function canForProduction(PersonId $personId, ProductionId $productionId, string $capability): bool
    {
        return isset($this->grants[$this->key($personId, $productionId, $capability)]);
    }

    private function key(PersonId $personId, ProductionId $productionId, string $capability): string
    {
        return $personId->toString() . '|' . $productionId->toString() . '|' . $capability;
    }
}
