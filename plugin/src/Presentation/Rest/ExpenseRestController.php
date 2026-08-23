<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Account\AccountNotFoundException;
use StageArt\Application\Expense\ConfirmExpenseCommand;
use StageArt\Application\Expense\ConfirmExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseCommand;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseAccessDeniedException;
use StageArt\Application\Expense\ExpenseNotFoundException;
use StageArt\Application\Expense\GetExpenseQuery;
use StageArt\Application\Expense\GetExpenseUseCase;
use StageArt\Application\Expense\ListExpensesUseCase;
use StageArt\Application\Expense\ListProductionExpensesQuery;
use StageArt\Application\Expense\UpdateExpenseCommand;
use StageArt\Application\Expense\UpdateExpenseUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ExpenseRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateExpenseUseCase $createExpense;
    private UpdateExpenseUseCase $updateExpense;
    private ConfirmExpenseUseCase $confirmExpense;
    private GetExpenseUseCase $getExpense;
    private ListExpensesUseCase $listExpenses;

    public function __construct(
        CreateExpenseUseCase $createExpense,
        UpdateExpenseUseCase $updateExpense,
        ConfirmExpenseUseCase $confirmExpense,
        GetExpenseUseCase $getExpense,
        ListExpensesUseCase $listExpenses
    ) {
        $this->createExpense = $createExpense;
        $this->updateExpense = $updateExpense;
        $this->confirmExpense = $confirmExpense;
        $this->getExpense = $getExpense;
        $this->listExpenses = $listExpenses;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/expenses', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/expenses/(?P<id>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/expenses/(?P<id>[^/]+)/confirm', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'confirm'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
    }

    public function require_login(): bool
    {
        return is_user_logged_in();
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListProductionExpensesQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listExpenses->execute($query)),
                200
            );
        } catch (ExpenseAccessDeniedException $exception) {
            return new WP_Error('stageart_expense_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateExpenseCommand(
                get_current_user_id(),
                (string) $request->get_param('id'),
                (array) $request->get_param('lines'),
                $this->stringOrNull($request->get_param('payer_person_id'))
            );

            return new WP_REST_Response($this->createExpense->execute($command)->toArray(), 201);
        } catch (ExpenseAccessDeniedException $exception) {
            return new WP_Error('stageart_expense_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_expense_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetExpenseQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getExpense->execute($query)->toArray(), 200);
        } catch (ExpenseAccessDeniedException $exception) {
            return new WP_Error('stageart_expense_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ExpenseNotFoundException $exception) {
            return new WP_Error('stageart_expense_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateExpenseCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (array) $request->get_param('lines')
            );

            return new WP_REST_Response($this->updateExpense->execute($command)->toArray(), 200);
        } catch (ExpenseAccessDeniedException $exception) {
            return new WP_Error('stageart_expense_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ExpenseNotFoundException $exception) {
            return new WP_Error('stageart_expense_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_expense_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function confirm(WP_REST_Request $request)
    {
        try {
            $command = new ConfirmExpenseCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('payable_account_id')
            );

            return new WP_REST_Response($this->confirmExpense->execute($command)->toArray(), 200);
        } catch (ExpenseAccessDeniedException $exception) {
            return new WP_Error('stageart_expense_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ExpenseNotFoundException $exception) {
            return new WP_Error('stageart_expense_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (AccountNotFoundException $exception) {
            return new WP_Error('stageart_account_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_expense_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @param mixed $value
     */
    private function stringOrNull($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
