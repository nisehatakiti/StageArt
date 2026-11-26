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

/** Listing a Production's Join Keys reveals codes that work for joining,
 * so it needs the same gate as issuing/disabling one:
 * ProductionAuthorizationService::canManageJoinKeys() (the PrimaryManager
 * or this Production's 公演代理人; not 稽古担当). Organization Roles
 * grant nothing here (Production Scope has no Organization fallback).
 * Same check order as IssueProductionJoinKeyUseCase. Only the authorized
 * Production's keys are read, every status included. */
final class ListProductionJoinKeysUseCase
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

    /**
     * @return JoinKeyResult[]
     */
    public function execute(ListProductionJoinKeysQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canManageJoinKeys($person, $production)) {
            throw new ProductionAccessDeniedException('Only the PrimaryManager or the Production proxy manager can view Join Keys.');
        }

        return array_map(
            static fn (JoinKey $joinKey) => JoinKeyResult::fromDomain($joinKey),
            $this->joinKeys->findByTarget(JoinKey::TARGET_TYPE_PRODUCTION, $production->id()->toString())
        );
    }
}
