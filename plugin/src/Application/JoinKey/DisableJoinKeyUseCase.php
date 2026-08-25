<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyId;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/** Authorization branches on the JoinKey's own `targetType` - the same
 * Owner/PrimaryManager gate as issuance (see IssueOrganizationJoinKeyUseCase/
 * IssueProductionJoinKeyUseCase). Disabling never retroactively removes
 * any Membership/Participant already created through this key (JoinKey.md's
 * "Join Keyの無効化は既存のMembership/Participantを遡及的に削除しない"). */
final class DisableJoinKeyUseCase
{
    private JoinKeyRepositoryInterface $joinKeys;
    private ProductionRepositoryInterface $productions;
    private OrganizationAuthorizationService $organizationAuthorization;
    private ProductionAuthorizationService $productionAuthorization;

    public function __construct(
        JoinKeyRepositoryInterface $joinKeys,
        ProductionRepositoryInterface $productions,
        OrganizationAuthorizationService $organizationAuthorization,
        ProductionAuthorizationService $productionAuthorization
    ) {
        $this->joinKeys = $joinKeys;
        $this->productions = $productions;
        $this->organizationAuthorization = $organizationAuthorization;
        $this->productionAuthorization = $productionAuthorization;
    }

    public function execute(DisableJoinKeyCommand $command): JoinKeyResult
    {
        $joinKey = $this->joinKeys->findById(JoinKeyId::fromString($command->joinKeyId));

        if (! $joinKey) {
            throw new JoinKeyNotFoundException($command->joinKeyId);
        }

        if ($joinKey->targetType() === JoinKey::TARGET_TYPE_ORGANIZATION) {
            $this->assertCanManageOrganization($command->requestedByWordPressUserId, $joinKey->targetId());
        } else {
            $this->assertCanManageProduction($command->requestedByWordPressUserId, $joinKey->targetId());
        }

        $joinKey->disable();
        $this->joinKeys->save($joinKey);

        return JoinKeyResult::fromDomain($joinKey);
    }

    private function assertCanManageOrganization(int $wordPressUserId, string $organizationId): void
    {
        $person = $this->organizationAuthorization->resolveCurrentPerson($wordPressUserId);

        if (! $person || ! $this->organizationAuthorization->hasRole($person, OrganizationId::fromString($organizationId), [RoleKey::OWNER])) {
            throw new JoinKeyAccessDeniedException('Only an Organization Owner can disable this Join Key.');
        }
    }

    private function assertCanManageProduction(int $wordPressUserId, string $productionId): void
    {
        $person = $this->productionAuthorization->resolveCurrentPerson($wordPressUserId);
        $production = $person ? $this->productions->findById(ProductionId::fromString($productionId)) : null;

        if (! $person || ! $production || ! $this->productionAuthorization->canManageProduction($person, $production)) {
            throw new JoinKeyAccessDeniedException('Only the PrimaryManager can disable this Join Key.');
        }
    }
}
