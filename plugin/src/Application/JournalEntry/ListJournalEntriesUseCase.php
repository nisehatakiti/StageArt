<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

/**
 * JournalEntry.md "Privacy": "会計情報は、Organization内部の権限を持つPerson
 * のみが参照できる" - raw ledger detail is PrimaryManager-only, same as
 * Budget detail (see GetBudgetUseCase's docblock).
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (ProductionContext/Identity/Authorization), not on
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`
 * directly.
 */
final class ListJournalEntriesUseCase
{
    private JournalEntryRepositoryInterface $journalEntries;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        JournalEntryRepositoryInterface $journalEntries,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->journalEntries = $journalEntries;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    /**
     * @return JournalEntryResult[]
     */
    public function execute(ListProductionJournalEntriesQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new JournalEntryAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::MANAGE)) {
            throw new JournalEntryAccessDeniedException(
                'Only the PrimaryManager can view this Production\'s Journal Entries.'
            );
        }

        $entries = $this->journalEntries->findByProductionId($productionId);

        return array_map(static fn ($entry): JournalEntryResult => JournalEntryResult::fromDomain($entry), $entries);
    }
}
