<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Expense.md "Authorization": "Expenseの作成(DRAFT)は、公演に関与する幅広い
 * Personが行えることを想定する" - any Production Member, not just the
 * PrimaryManager (unlike Budget, which is management-only end to end).
 */
final class CreateExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ProductionOrganizationResolver $organizationResolver;
    private ExpenseLineFactory $lineFactory;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ProductionOrganizationResolver $organizationResolver,
        ExpenseLineFactory $lineFactory,
        TransactionManagerInterface $transactions
    ) {
        $this->expenses = $expenses;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->organizationResolver = $organizationResolver;
        $this->lineFactory = $lineFactory;
        $this->transactions = $transactions;
    }

    public function execute(CreateExpenseCommand $command): ExpenseResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new ExpenseAccessDeniedException('Only members of this Production can create an Expense.');
        }

        $organizationId = $this->organizationResolver->resolve($production);
        $lines = $this->lineFactory->build($command->lines, $organizationId);

        $payerPersonId = $command->payerPersonId !== null && $command->payerPersonId !== ''
            ? PersonId::fromString($command->payerPersonId)
            : null;

        $expense = $this->transactions->run(function () use ($production, $lines, $requester, $payerPersonId): Expense {
            $expense = Expense::create($production->id(), $lines, $requester->id(), $payerPersonId);
            $this->expenses->save($expense);

            return $expense;
        });

        return ExpenseResult::fromDomain($expense);
    }
}
