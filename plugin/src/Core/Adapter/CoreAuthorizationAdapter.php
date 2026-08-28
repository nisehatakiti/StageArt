<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\OrganizationCapability;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * StageArt Core/Module Architecture: the concrete "StageArt Adapter"
 * implementing AuthorizationContract for the current, single-host
 * StageArt Core - see docs/architecture/CoreModuleArchitecture.md's
 * "WordPress Adapter" section for the future swap this indirection
 * exists to make possible.
 *
 * Deliberately a thin wrapper: all the actual Role/Permission/
 * PrimaryManager logic still lives in ProductionAuthorizationService
 * (Core's own Application service, unchanged in substance by this
 * phase beyond the canManageRehearsals()/canManageAccounting() ->
 * hasProductionCapability() rename) - this Adapter only re-shapes that
 * service's `Person`/`Production`-Entity-based API into the Contract's
 * ID-based one (resolving both Entities itself so a Module never needs
 * to hold them just to ask an authorization question), and returns
 * `false` if either side of the check cannot even be resolved.
 */
final class CoreAuthorizationAdapter implements AuthorizationContract
{
    private ProductionAuthorizationService $productionAuthorization;
    private ProductionRepositoryInterface $productions;
    private PersonRepositoryInterface $people;

    public function __construct(
        ProductionAuthorizationService $productionAuthorization,
        ProductionRepositoryInterface $productions,
        PersonRepositoryInterface $people
    ) {
        $this->productionAuthorization = $productionAuthorization;
        $this->productions = $productions;
        $this->people = $people;
    }

    public function resolveCurrentPersonId(int $wordPressUserId): ?PersonId
    {
        $person = $this->productionAuthorization->resolveCurrentPerson($wordPressUserId);

        return $person?->id();
    }

    public function canForProduction(PersonId $personId, ProductionId $productionId, string $capability): bool
    {
        $person = $this->people->findById($personId);
        $production = $this->productions->findById($productionId);

        if ($person === null || $production === null) {
            return false;
        }

        return $this->productionAuthorization->hasProductionCapability($person, $production, $capability);
    }

    public function canForOrganization(PersonId $personId, OrganizationId $organizationId, string $capability): bool
    {
        $person = $this->people->findById($personId);

        if ($person === null) {
            return false;
        }

        $allowedRoleKeys = $this->roleKeysGranting($capability);

        if ($allowedRoleKeys === []) {
            return false;
        }

        return $this->productionAuthorization->hasOrganizationRole($person, $organizationId, $allowedRoleKeys);
    }

    /**
     * @return string[]
     */
    private function roleKeysGranting(string $capability): array
    {
        return match ($capability) {
            OrganizationCapability::OWNER => [RoleKey::OWNER],
            OrganizationCapability::MEMBER => [RoleKey::OWNER, RoleKey::MEMBER],
            default => [],
        };
    }
}
