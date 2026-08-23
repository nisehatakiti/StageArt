<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class ListExpensesUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->expenses = $expenses;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    /**
     * @return ExpenseResult[]
     */
    public function execute(ListProductionExpensesQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new ExpenseAccessDeniedException('Only members of this Production can view its Expenses.');
        }

        $expenses = $this->expenses->findByProductionId($production->id());

        return array_map(static fn ($expense): ExpenseResult => ExpenseResult::fromDomain($expense), $expenses);
    }
}
