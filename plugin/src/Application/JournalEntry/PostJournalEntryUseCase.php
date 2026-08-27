<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\OrganizationCapability;
use StageArt\Core\Contract\ProductionContextContract;
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
 * StageArt Core/Module Architecture Phase 3: fully depends on Core
 * Contracts now, including the Organization-Scope branch (a JournalEntry
 * not tied to any Production) - `AuthorizationContract::canForOrganization()`
 * (new this phase, see `Core\Contract\OrganizationCapability`) closes
 * the one disclosed exception Phase 2 left open, where this branch still
 * called `OrganizationAuthorizationService::hasRole()` directly.
 */
final class PostJournalEntryUseCase
{
    private JournalEntryRepositoryInterface $journalEntries;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        JournalEntryRepositoryInterface $journalEntries,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->journalEntries = $journalEntries;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(PostJournalEntryCommand $command): JournalEntryResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
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

            if (! $this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::MANAGE)) {
                throw new JournalEntryAccessDeniedException('Only the PrimaryManager can post this JournalEntry.');
            }
        } elseif (! $this->authorization->canForOrganization($requesterId, $entry->organizationId(), OrganizationCapability::OWNER)) {
            throw new JournalEntryAccessDeniedException('Only the Organization Owner can post this JournalEntry.');
        }

        $this->transactions->run(function () use ($entry, $requesterId): void {
            $entry->post($requesterId);
            $this->journalEntries->save($entry);
        });

        return JournalEntryResult::fromDomain($entry);
    }
}
