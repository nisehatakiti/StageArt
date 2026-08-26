<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * JournalEntry.md "Privacy": "会計情報は、Organization内部の権限を持つPerson
 * のみが参照できる" - raw ledger detail is PrimaryManager-only, same as
 * Budget detail (see GetBudgetUseCase's docblock).
 */
final class ListJournalEntriesUseCase
{
    private JournalEntryRepositoryInterface $journalEntries;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        JournalEntryRepositoryInterface $journalEntries,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->journalEntries = $journalEntries;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    /**
     * @return JournalEntryResult[]
     */
    public function execute(ListProductionJournalEntriesQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new JournalEntryAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->hasProductionCapability($requester, $production, AccountingCapability::MANAGE)) {
            throw new JournalEntryAccessDeniedException(
                'Only the PrimaryManager can view this Production\'s Journal Entries.'
            );
        }

        $entries = $this->journalEntries->findByProductionId($production->id());

        return array_map(static fn ($entry): JournalEntryResult => JournalEntryResult::fromDomain($entry), $entries);
    }
}
