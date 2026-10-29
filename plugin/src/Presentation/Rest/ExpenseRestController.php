<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Account\AccountNotFoundException;
use StageArt\Application\Expense\ApplicantExpenseTransitionUseCase;
use StageArt\Application\Expense\ApproveExpenseCommand;
use StageArt\Application\Expense\ApproveExpenseUseCase;
use StageArt\Application\Expense\ConfirmExpenseCommand;
use StageArt\Application\Expense\ConfirmExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseCommand;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseAccessDeniedException;
use StageArt\Application\Expense\ExpenseAccountingDisabledException;
use StageArt\Application\Expense\ExpenseNotFoundException;
use StageArt\Application\Expense\ExpenseOperationRetiredException;
use StageArt\Application\Expense\ExpenseResult;
use StageArt\Application\Expense\ExpenseTransitionCommand;
use StageArt\Application\Expense\GetExpenseQuery;
use StageArt\Application\Expense\GetExpenseUseCase;
use StageArt\Application\Expense\ListExpensesUseCase;
use StageArt\Application\Expense\ListProductionExpensesQuery;
use StageArt\Application\Expense\RejectExpenseCommand;
use StageArt\Application\Expense\RejectExpenseUseCase;
use StageArt\Application\Expense\UpdateExpenseCommand;
use StageArt\Application\Expense\UpdateExpenseUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Expense\ExpenseStateConflictException;
use Throwable;
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
    private ApplicantExpenseTransitionUseCase $applicantTransitions;
    private ApproveExpenseUseCase $approveExpense;
    private RejectExpenseUseCase $rejectExpense;

    public function __construct(
        CreateExpenseUseCase $createExpense,
        UpdateExpenseUseCase $updateExpense,
        ConfirmExpenseUseCase $confirmExpense,
        GetExpenseUseCase $getExpense,
        ListExpensesUseCase $listExpenses,
        ApplicantExpenseTransitionUseCase $applicantTransitions,
        ApproveExpenseUseCase $approveExpense,
        RejectExpenseUseCase $rejectExpense
    ) {
        $this->createExpense = $createExpense;
        $this->updateExpense = $updateExpense;
        $this->confirmExpense = $confirmExpense;
        $this->getExpense = $getExpense;
        $this->listExpenses = $listExpenses;
        $this->applicantTransitions = $applicantTransitions;
        $this->approveExpense = $approveExpense;
        $this->rejectExpense = $rejectExpense;
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

        // 修正8 approval workflow. Every authorization decision is made by
        // the Use Case; require_login only rejects anonymous requests.
        foreach (['submit', 'withdraw', 'resubmit', 'approve', 'reject'] as $action) {
            register_rest_route(self::API_NAMESPACE, '/expenses/(?P<id>[^/]+)/' . $action, [
                [
                    'methods' => 'PATCH',
                    'callback' => [$this, $action],
                    'permission_callback' => [$this, 'require_login'],
                ],
            ]);
        }
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
            $query = new ListProductionExpensesQuery(
                (string) $request->get_param('id'),
                get_current_user_id(),
                $this->stringOrNull($request->get_param('status'))
            );

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listExpenses->execute($query)),
                200
            );
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
        } catch (Throwable $exception) {
            return $this->workflowError($exception);
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
        } catch (ExpenseOperationRetiredException $exception) {
            return new WP_Error('stageart_expense_confirm_retired', $exception->getMessage(), ['status' => 410]);
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
     * @return WP_REST_Response|WP_Error
     */
    public function submit(WP_REST_Request $request)
    {
        return $this->respond(fn (): ExpenseResult => $this->applicantTransitions->submit($this->transitionCommand($request)));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function withdraw(WP_REST_Request $request)
    {
        return $this->respond(fn (): ExpenseResult => $this->applicantTransitions->withdraw($this->transitionCommand($request)));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function resubmit(WP_REST_Request $request)
    {
        return $this->respond(fn (): ExpenseResult => $this->applicantTransitions->resubmit($this->transitionCommand($request)));
    }

    /**
     * Body: { revision: int (required - the submission revision the
     * approver reviewed), approved_amount?: int }.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function approve(WP_REST_Request $request)
    {
        $revision = $request->get_param('revision');

        if (! is_numeric($revision)) {
            return new WP_Error('stageart_expense_invalid', 'revision (the submission revision being approved) is required.', ['status' => 422]);
        }

        $approvedAmount = $request->get_param('approved_amount');

        if ($approvedAmount !== null && $approvedAmount !== '' && ! is_numeric($approvedAmount)) {
            return new WP_Error('stageart_expense_invalid', 'approved_amount must be an integer.', ['status' => 422]);
        }

        return $this->respond(fn (): ExpenseResult => $this->approveExpense->execute(new ApproveExpenseCommand(
            (string) $request->get_param('id'),
            get_current_user_id(),
            (int) $revision,
            $approvedAmount === null || $approvedAmount === '' ? null : (int) $approvedAmount
        )));
    }

    /**
     * Body: { reason: string (required) }.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function reject(WP_REST_Request $request)
    {
        return $this->respond(fn (): ExpenseResult => $this->rejectExpense->execute(new RejectExpenseCommand(
            (string) $request->get_param('id'),
            get_current_user_id(),
            (string) $request->get_param('reason')
        )));
    }

    private function transitionCommand(WP_REST_Request $request): ExpenseTransitionCommand
    {
        return new ExpenseTransitionCommand((string) $request->get_param('id'), get_current_user_id());
    }

    /**
     * @param callable(): ExpenseResult $operation
     * @return WP_REST_Response|WP_Error
     */
    private function respond(callable $operation)
    {
        try {
            return new WP_REST_Response($operation()->toArray(), 200);
        } catch (Throwable $exception) {
            return $this->workflowError($exception);
        }
    }

    /**
     * 403 access / self-approval, 404 missing, 409 the Expense's current
     * status or revision does not allow it (already processed, edited
     * since review, accounting disabled), 422 invalid input. Anything else
     * is rethrown so WordPress reports it as a 500.
     *
     * @return WP_Error
     */
    private function workflowError(Throwable $exception): WP_Error
    {
        if ($exception instanceof ExpenseAccessDeniedException) {
            return new WP_Error('stageart_expense_access_denied', $exception->getMessage(), ['status' => 403]);
        }

        if ($exception instanceof ExpenseNotFoundException) {
            return new WP_Error('stageart_expense_not_found', $exception->getMessage(), ['status' => 404]);
        }

        if ($exception instanceof ProductionNotFoundException) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }

        if ($exception instanceof ExpenseStateConflictException) {
            return new WP_Error('stageart_expense_conflict', $exception->getMessage(), ['status' => 409]);
        }

        if ($exception instanceof ExpenseAccountingDisabledException) {
            return new WP_Error('stageart_accounting_disabled', $exception->getMessage(), ['status' => 409]);
        }

        if ($exception instanceof InvalidArgumentException) {
            return new WP_Error('stageart_expense_invalid', $exception->getMessage(), ['status' => 422]);
        }

        throw $exception;
    }

    /**
     * @param mixed $value
     */
    private function stringOrNull($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
