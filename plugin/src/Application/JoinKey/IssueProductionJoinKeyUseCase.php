<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/** JoinKey.md's "Issuer": the Production's PrimaryManager or its 公演代理人
 * may issue a Production Join Key (ProductionAuthorizationService::
 * canManageJoinKeys(), the same gate as listing and disabling). 稽古担当 and
 * other single-Role delegates may not. */
final class IssueProductionJoinKeyUseCase
{
    private ProductionRepositoryInterface $productions;
    private JoinKeyRepositoryInterface $joinKeys;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ProductionRepositoryInterface $productions,
        JoinKeyRepositoryInterface $joinKeys,
        ProductionAuthorizationService $authorization
    ) {
        $this->productions = $productions;
        $this->joinKeys = $joinKeys;
        $this->authorization = $authorization;
    }

    public function execute(IssueProductionJoinKeyCommand $command): JoinKeyResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageJoinKeys($person, $production)) {
            throw new ProductionAccessDeniedException('Only the PrimaryManager or the Production proxy manager can issue a Join Key.');
        }

        $joinKey = JoinKey::issueForProduction($production->id()->toString(), $person->id());
        $this->joinKeys->save($joinKey);

        return JoinKeyResult::fromDomain($joinKey);
    }
}
