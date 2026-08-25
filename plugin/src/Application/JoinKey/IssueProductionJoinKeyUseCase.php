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

/** JoinKey.md's "Issuer": only the Production's PrimaryManager may issue
 * a Production Join Key. */
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

        if (! $this->authorization->canManageProduction($person, $production)) {
            throw new ProductionAccessDeniedException('Only the PrimaryManager can issue a Join Key.');
        }

        $joinKey = JoinKey::issueForProduction($production->id()->toString(), $person->id());
        $this->joinKeys->save($joinKey);

        return JoinKeyResult::fromDomain($joinKey);
    }
}
