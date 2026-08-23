<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/**
 * Production does not reference OrganizationId directly - only Project
 * does (Production -> Project -> Organization). Account.md ("Account
 * self体はProductionに所属しない...AccountはOrganizationの会計分類マスタ")
 * and JournalEntry.md ("Journal EntryはOrganizationに所属する") both need
 * this resolution to validate that a Production-scoped Budget/Expense/
 * JournalEntry only ever references Accounts from its own Organization.
 * Shared by CreateBudgetUseCase, ConfirmExpenseUseCase, and
 * GetProductionAccountingSummaryUseCase rather than duplicated three
 * times.
 */
final class ProductionOrganizationResolver
{
    private ProjectRepositoryInterface $projects;

    public function __construct(ProjectRepositoryInterface $projects)
    {
        $this->projects = $projects;
    }

    public function resolve(Production $production): OrganizationId
    {
        $project = $this->projects->findById($production->projectId());

        if (! $project) {
            throw new ProjectNotFoundException($production->projectId()->toString());
        }

        return $project->organizationId();
    }
}
