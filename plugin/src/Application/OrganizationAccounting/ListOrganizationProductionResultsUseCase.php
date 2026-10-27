<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationAccounting;

use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\OrganizationCapability;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Production\ProductionStatus;

/**
 * 団体会計の公演別実績 (OrganizationAccountingAndProductionCostPolicy.md §2):
 * per Production of the Organization, 売上 / 原価 / 収支 and whether its
 * 決算 is confirmed, plus the totals.
 *
 * Read from the same POSTED Journal Entries the Production's own 会計
 * reads (AccountingPolicy.md "Production and Organization Consistency") -
 * nothing is copied or posted for this list. 売上 is the REVENUE Lines,
 * 原価 the EXPENSE Lines, 収支 their difference; ASSET / LIABILITY /
 * EQUITY Lines are not part of it. The 費目別 detail stays in the
 * Production's 会計 (GetProductionAccountingDetailUseCase).
 *
 * 決算確定済み = the Production reached COMPLETED (or later ARCHIVED),
 * the Lifecycle step "決算完了" (ProductionLifecycle.md).
 *
 * 仕掛品 (§4) is not implemented: its accounts, Journal Date and the
 * costs it covers are still open (§7), so no entry here is a 仕掛品
 * transfer yet.
 *
 * Visibility: the Organization Owner.
 */
final class ListOrganizationProductionResultsUseCase
{
    private const CURRENCY = 'JPY';

    private JournalEntryRepositoryInterface $journalEntries;
    private AccountRepositoryInterface $accounts;
    private ProductionContextContract $productionContext;
    private OrganizationContextContract $organizationContext;
    private AuthorizationContract $authorization;
    private IdentityContract $identity;

    public function __construct(
        JournalEntryRepositoryInterface $journalEntries,
        AccountRepositoryInterface $accounts,
        ProductionContextContract $productionContext,
        OrganizationContextContract $organizationContext,
        AuthorizationContract $authorization,
        IdentityContract $identity
    ) {
        $this->journalEntries = $journalEntries;
        $this->accounts = $accounts;
        $this->productionContext = $productionContext;
        $this->organizationContext = $organizationContext;
        $this->authorization = $authorization;
        $this->identity = $identity;
    }

    public function execute(ListOrganizationProductionResultsQuery $query): OrganizationProductionResultsResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new OrganizationAccountingAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $this->organizationContext->organizationExists($organizationId)) {
            throw new OrganizationNotFoundException($query->organizationId);
        }

        if (! $this->authorization->canForOrganization($requesterId, $organizationId, OrganizationCapability::OWNER)) {
            throw new OrganizationAccountingAccessDeniedException(
                'Only the Owner can view this Organization\'s accounting.'
            );
        }

        $entries = $this->journalEntries->findPostedWithProductionByOrganizationId($organizationId);

        $accountIds = [];
        foreach ($entries as $entry) {
            foreach ($entry->lines() as $line) {
                $accountIds[$line->accountId()->toString()] = $line->accountId();
            }
        }
        $accountsById = $this->bulkFetchAccountsById(array_values($accountIds));

        $revenueByProduction = [];
        $costByProduction = [];
        foreach ($entries as $entry) {
            $productionKey = $entry->productionId()->toString();

            foreach ($entry->lines() as $line) {
                $account = $accountsById[$line->accountId()->toString()] ?? null;
                if ($account === null) {
                    continue;
                }

                $isDebit = $line->debitCredit()->equals(DebitCredit::debit());

                if ($account->type()->equals(AccountType::fromString(AccountType::REVENUE))) {
                    $revenueByProduction[$productionKey] = ($revenueByProduction[$productionKey] ?? 0) + ($isDebit ? -$line->amount() : $line->amount());
                } elseif ($account->type()->equals(AccountType::fromString(AccountType::EXPENSE))) {
                    $costByProduction[$productionKey] = ($costByProduction[$productionKey] ?? 0) + ($isDebit ? $line->amount() : -$line->amount());
                }
            }
        }

        $rows = [];
        $totalRevenue = 0;
        $totalCost = 0;
        foreach ($this->productionContext->getOrganizationProductions($organizationId) as $production) {
            $key = $production->id->toString();
            $revenue = $revenueByProduction[$key] ?? 0;
            $cost = $costByProduction[$key] ?? 0;
            $totalRevenue += $revenue;
            $totalCost += $cost;

            $rows[] = [
                'production_id' => $key,
                'name' => $production->name,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $revenue - $cost,
                'closing_confirmed' => in_array($production->status, [ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED], true),
            ];
        }

        return new OrganizationProductionResultsResult(
            $organizationId->toString(),
            $rows,
            ['revenue' => $totalRevenue, 'cost' => $totalCost, 'profit' => $totalRevenue - $totalCost],
            self::CURRENCY
        );
    }

    /**
     * @param AccountId[] $accountIds
     * @return array<string, Account>
     */
    private function bulkFetchAccountsById(array $accountIds): array
    {
        if (count($accountIds) === 0) {
            return [];
        }

        $byId = [];
        foreach ($this->accounts->findByIds($accountIds) as $account) {
            $byId[$account->id()->toString()] = $account;
        }

        return $byId;
    }
}
