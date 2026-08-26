<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\JournalEntry\JournalEntryId;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;

/**
 * JournalEntry.md "Journal Entry Posting Timing": "Journal EntryのPOSTED
 * への遷移は...会計管理権限を持つPersonによる操作として行う" - a general,
 * per-entry operation available to accounting-permission holders,
 * independent of Production Settlement's bulk-post convenience
 * (explicitly out of scope this Phase - see Production::changeStatus()'s
 * existing comment blocking COMPLETED->ARCHIVED without it). Without
 * this endpoint, Expense-Confirmed-generated Journal Entries (created
 * DRAFT per Blueprint) could never reach Actual, which would leave this
 * Phase's stated goal - a working Budget/Actual/Variance pipeline -
 * unreachable. Disclosed as a judgment call, not a Blueprint-mandated
 * API.
 *
 * StageArt Core/Module Architecture Phase 2: the Production-Scope branch
 * depends on Core Contracts (ProductionContext/Authorization), not
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`
 * directly. The Organization-Scope branch (a JournalEntry not tied to
 * any Production - e.g. an Organization-level ledger entry) still
 * depends on `OrganizationAuthorizationService` directly: it needs
 * `hasRole()`, a Role-based check against a `Person` Entity, which has
 * no equivalent on `AuthorizationContract` (deliberately Capability-
 * string-based and Production-scoped only, per its own docblock).
 * Adding an Organization-scope Capability Contract method is real
 * design work with no other current caller - left as a disclosed,
 * intentionally out-of-scope gap for this phase rather than guessed at.
 */
final class PostJournalEntryUseCase
{
    private JournalEntryRepositoryInterface $journalEntries;
    private ProductionContextContract $productionContext;
    private OrganizationAuthorizationService $organizationAuthorization;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        JournalEntryRepositoryInterface $journalEntries,
        ProductionContextContract $productionContext,
        OrganizationAuthorizationService $organizationAuthorization,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->journalEntries = $journalEntries;
        $this->productionContext = $productionContext;
        $this->organizationAuthorization = $organizationAuthorization;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(PostJournalEntryCommand $command): JournalEntryResult
    {
        $requester = $this->organizationAuthorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new JournalEntryAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $entry = $this->journalEntries->findById(JournalEntryId::fromString($command->journalEntryId));

        if (! $entry) {
            throw new JournalEntryNotFoundException($command->journalEntryId);
        }

        if ($entry->productionId() !== null) {
            $productionId = $entry->productionId();
            $production = $this->productionContext->getProduction($productionId);

            if (! $production) {
                throw new ProductionNotFoundException($productionId->toString());
            }

            if (! $this->authorization->canForProduction($requester->id(), $productionId, AccountingCapability::MANAGE)) {
                throw new JournalEntryAccessDeniedException('Only the PrimaryManager can post this JournalEntry.');
            }
        } elseif (! $this->organizationAuthorization->hasRole($requester, $entry->organizationId(), [RoleKey::OWNER])) {
            throw new JournalEntryAccessDeniedException('Only the Organization Owner can post this JournalEntry.');
        }

        $this->transactions->run(function () use ($entry, $requester): void {
            $entry->post($requester->id());
            $this->journalEntries->save($entry);
        });

        return JournalEntryResult::fromDomain($entry);
    }
}
