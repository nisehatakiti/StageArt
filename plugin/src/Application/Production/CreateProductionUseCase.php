<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Project\ProjectId;
use StageArt\Domain\Project\ProjectRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Creation itself is gated at Organization Scope (only an Organization
 * Owner may create a Production in one of their Projects), the same
 * principle CreateProjectUseCase uses for creating a Project: this is
 * "establishing a new resource within the Organization's structure," not
 * an ongoing Production-Scope operation, so it does not conflict with
 * Production Scope's PrimaryManager/ProductionDelegate-only access rule
 * for existing Productions (see ProductionAuthorizationService).
 *
 * The PrimaryManager named at creation must already have an ACTIVE
 * Membership in the Production's Organization and an ACTIVE UserAccount
 * (Phase 1 instruction §6 and Production.md's "Primary Manager
 * UserAccount Requirement") - this Use Case never creates a Membership
 * or UserAccount as a side effect, mirroring OwnerTransferUseCase's
 * refusal to provision either for an incoming Owner.
 */
final class CreateProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProjectRepositoryInterface $projects;
    private MembershipRepositoryInterface $memberships;
    private UserAccountRepositoryInterface $userAccounts;
    private OrganizationAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProjectRepositoryInterface $projects,
        MembershipRepositoryInterface $memberships,
        UserAccountRepositoryInterface $userAccounts,
        OrganizationAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->projects = $projects;
        $this->memberships = $memberships;
        $this->userAccounts = $userAccounts;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(CreateProductionCommand $command): ProductionResult
    {
        $slug = new ProductionSlug($command->slug);

        // StageArt Web First Phase 2: pre-check for a clean 422 in the
        // common case; the DB's own UNIQUE KEY slug (slug) constraint
        // (Installer.php) is the ultimate backstop against the rare
        // concurrent-write race this check alone cannot fully close.
        if ($this->productions->findBySlug($slug->toString()) !== null) {
            throw new ProductionSlugAlreadyTakenException($slug->toString());
        }

        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $project = $this->projects->findById(ProjectId::fromString($command->projectId));

        if (! $project) {
            throw new ProjectNotFoundException($command->projectId);
        }

        if (! $this->authorization->hasRole($requester, $project->organizationId(), [RoleKey::OWNER])) {
            throw new ProductionAccessDeniedException('Only an Organization Owner can create a Production in this Project.');
        }

        $primaryManagerPersonId = PersonId::fromString($command->primaryManagerPersonId);

        $primaryManagerMembership = $this->memberships->findByOrganizationAndPerson(
            $project->organizationId(),
            $primaryManagerPersonId
        );

        if (! $primaryManagerMembership || ! $primaryManagerMembership->isActive()) {
            throw new PrimaryManagerNotEligibleException(
                'The PrimaryManager must have an ACTIVE Membership in this Production\'s Organization.'
            );
        }

        $primaryManagerUserAccount = $this->userAccounts->findByPersonId($primaryManagerPersonId);

        if (! $primaryManagerUserAccount || ! $primaryManagerUserAccount->isActive()) {
            throw new PrimaryManagerNotEligibleException('The PrimaryManager must have an ACTIVE UserAccount.');
        }

        $production = $this->transactions->run(function () use ($project, $command, $primaryManagerPersonId, $slug): Production {
            $production = Production::create(
                $project->id(),
                new ProductionName($command->name),
                $primaryManagerPersonId,
                $command->titleHeading,
                $slug
            );

            $this->productions->save($production);

            return $production;
        });

        return ProductionResult::fromDomain(
            $production,
            $requester->id()->equals($primaryManagerPersonId),
            null
        );
    }
}
