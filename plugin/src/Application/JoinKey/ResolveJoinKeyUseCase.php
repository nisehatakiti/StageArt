<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/** Read-only preview - does NOT call recordUse() (see JoinKey.md's
 * "Resolve and Confirm": showing the target is a separate step from
 * actually using the key, which only happens once the Person confirms
 * and a Membership/Participant request is actually created - see
 * RequestOrganizationMembershipUseCase/RequestProductionParticipationUseCase). */
final class ResolveJoinKeyUseCase
{
    private JoinKeyRepositoryInterface $joinKeys;
    private OrganizationRepositoryInterface $organizations;
    private ProductionRepositoryInterface $productions;

    public function __construct(
        JoinKeyRepositoryInterface $joinKeys,
        OrganizationRepositoryInterface $organizations,
        ProductionRepositoryInterface $productions
    ) {
        $this->joinKeys = $joinKeys;
        $this->organizations = $organizations;
        $this->productions = $productions;
    }

    public function execute(ResolveJoinKeyQuery $query): ResolvedJoinKeyResult
    {
        $normalized = JoinKey::normalizeCode($query->rawCode);
        $joinKey = $this->joinKeys->findByCode($normalized);

        if (! $joinKey || ! $joinKey->isUsable()) {
            throw new JoinKeyNotFoundException($query->rawCode);
        }

        if ($joinKey->targetType() === JoinKey::TARGET_TYPE_ORGANIZATION) {
            $organization = $this->organizations->findById(OrganizationId::fromString($joinKey->targetId()));

            if (! $organization) {
                throw new JoinKeyNotFoundException($query->rawCode);
            }

            return new ResolvedJoinKeyResult(
                $joinKey->id()->toString(),
                JoinKey::TARGET_TYPE_ORGANIZATION,
                $organization->id()->toString(),
                $organization->name()->toString(),
                $organization->slug()?->toString()
            );
        }

        $production = $this->productions->findById(ProductionId::fromString($joinKey->targetId()));

        if (! $production) {
            throw new JoinKeyNotFoundException($query->rawCode);
        }

        return new ResolvedJoinKeyResult(
            $joinKey->id()->toString(),
            JoinKey::TARGET_TYPE_PRODUCTION,
            $production->id()->toString(),
            $production->name()->toString(),
            $production->slug()?->toString()
        );
    }
}
