<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\JournalEntry\JournalEntryId;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Production\ProductionRepositoryInterface;

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
 */
final class PostJournalEntryUseCase
{
    private JournalEntryRepositoryInterface $journalEntries;
    private ProductionRepositoryInterface $productions;
    private OrganizationAuthorizationService $organizationAuthorization;
    private ProductionAuthorizationService $productionAuthorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        JournalEntryRepositoryInterface $journalEntries,
        ProductionRepositoryInterface $productions,
        OrganizationAuthorizationService $organizationAuthorization,
        ProductionAuthorizationService $productionAuthorization,
        TransactionManagerInterface $transactions
    ) {
        $this->journalEntries = $journalEntries;
        $this->productions = $productions;
        $this->organizationAuthorization = $organizationAuthorization;
        $this->productionAuthorization = $productionAuthorization;
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
            $production = $this->productions->findById($entry->productionId());

            if (! $production) {
                throw new ProductionNotFoundException($entry->productionId()->toString());
            }

            if (! $this->productionAuthorization->hasProductionCapability($requester, $production, AccountingCapability::MANAGE)) {
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
