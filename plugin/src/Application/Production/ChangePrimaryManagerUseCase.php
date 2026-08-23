<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Project\ProjectRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Mirrors OwnerTransferUseCase's shape for the equivalent Production
 * Scope operation: only the current PrimaryManager may execute the
 * change; the new PrimaryManager must already have an ACTIVE Membership
 * in the Production's Organization and an ACTIVE UserAccount; no
 * Membership or UserAccount is auto-created. Per Phase 1 instruction §7,
 * the outgoing PrimaryManager's other relationships (Participant,
 * Membership) are left untouched - this Use Case only reassigns
 * primaryManagerPersonId.
 */
final class ChangePrimaryManagerUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProjectRepositoryInterface $projects;
    private MembershipRepositoryInterface $memberships;
    private UserAccountRepositoryInterface $userAccounts;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProjectRepositoryInterface $projects,
        MembershipRepositoryInterface $memberships,
        UserAccountRepositoryInterface $userAccounts,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->projects = $projects;
        $this->memberships = $memberships;
        $this->userAccounts = $userAccounts;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(ChangePrimaryManagerCommand $command): ProductionResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->isPrimaryManager($requester, $production)) {
            throw new ProductionAccessDeniedException('Only the current PrimaryManager can change the PrimaryManager.');
        }

        $newPrimaryManagerPersonId = PersonId::fromString($command->newPrimaryManagerPersonId);

        if ($production->primaryManagerPersonId()->equals($newPrimaryManagerPersonId)) {
            throw new PrimaryManagerNotEligibleException('The new PrimaryManager is already the current PrimaryManager.');
        }

        $project = $this->projects->findById($production->projectId());

        if (! $project) {
            throw new ProjectNotFoundException($production->projectId()->toString());
        }

        $newPrimaryManagerMembership = $this->memberships->findByOrganizationAndPerson(
            $project->organizationId(),
            $newPrimaryManagerPersonId
        );

        if (! $newPrimaryManagerMembership || ! $newPrimaryManagerMembership->isActive()) {
            throw new PrimaryManagerNotEligibleException(
                'The new PrimaryManager must have an ACTIVE Membership in this Production\'s Organization.'
            );
        }

        $newPrimaryManagerUserAccount = $this->userAccounts->findByPersonId($newPrimaryManagerPersonId);

        if (! $newPrimaryManagerUserAccount || ! $newPrimaryManagerUserAccount->isActive()) {
            throw new PrimaryManagerNotEligibleException('The new PrimaryManager must have an ACTIVE UserAccount.');
        }

        $this->transactions->run(function () use ($production, $newPrimaryManagerPersonId): void {
            $production->changePrimaryManager($newPrimaryManagerPersonId);
            $this->productions->save($production);
        });

        return ProductionResult::fromDomain(
            $production,
            $requester->id()->equals($newPrimaryManagerPersonId),
            $this->authorization->activeDelegateFor($requester, $production)
        );
    }
}
