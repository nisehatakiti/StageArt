<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Budget\ActivateBudgetCommand;
use StageArt\Application\Budget\ActivateBudgetUseCase;
use StageArt\Application\Budget\BudgetAccessDeniedException;
use StageArt\Application\Budget\BudgetNotFoundException;
use StageArt\Application\Budget\CreateBudgetCommand;
use StageArt\Application\Budget\CreateBudgetUseCase;
use StageArt\Application\Budget\GetBudgetQuery;
use StageArt\Application\Budget\GetBudgetUseCase;
use StageArt\Application\Budget\ListBudgetsUseCase;
use StageArt\Application\Budget\ListProductionBudgetsQuery;
use StageArt\Application\Budget\UpdateBudgetCommand;
use StageArt\Application\Budget\UpdateBudgetUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class BudgetRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateBudgetUseCase $createBudget;
    private UpdateBudgetUseCase $updateBudget;
    private GetBudgetUseCase $getBudget;
    private ListBudgetsUseCase $listBudgets;
    private ActivateBudgetUseCase $activateBudget;

    public function __construct(
        CreateBudgetUseCase $createBudget,
        UpdateBudgetUseCase $updateBudget,
        GetBudgetUseCase $getBudget,
        ListBudgetsUseCase $listBudgets,
        ActivateBudgetUseCase $activateBudget
    ) {
        $this->createBudget = $createBudget;
        $this->updateBudget = $updateBudget;
        $this->getBudget = $getBudget;
        $this->listBudgets = $listBudgets;
        $this->activateBudget = $activateBudget;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/budgets', [
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

        register_rest_route(self::API_NAMESPACE, '/budgets/(?P<id>[^/]+)', [
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

        register_rest_route(self::API_NAMESPACE, '/budgets/(?P<id>[^/]+)/activate', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'activate'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
    }

    public function require_login(): bool
    {
        return is_user_logged_in();
    }

    /**
     * @return WP_REST_Response
     */
    public function list(WP_REST_Request $request)
    {
        $query = new ListProductionBudgetsQuery((string) $request->get_param('id'), get_current_user_id());

        try {
            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listBudgets->execute($query)),
                200
            );
        } catch (BudgetAccessDeniedException $exception) {
            return new WP_Error('stageart_budget_access_denied', $exception->getMessage(), ['status' => 403]);
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
            $command = new CreateBudgetCommand(
                get_current_user_id(),
                (string) $request->get_param('id'),
                (string) $request->get_param('name'),
                (array) $request->get_param('lines')
            );

            return new WP_REST_Response($this->createBudget->execute($command)->toArray(), 201);
        } catch (BudgetAccessDeniedException $exception) {
            return new WP_Error('stageart_budget_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_budget_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetBudgetQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getBudget->execute($query)->toArray(), 200);
        } catch (BudgetAccessDeniedException $exception) {
            return new WP_Error('stageart_budget_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (BudgetNotFoundException $exception) {
            return new WP_Error('stageart_budget_not_found', $exception->getMessage(), ['status' => 404]);
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
            $command = new UpdateBudgetCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('name'),
                (array) $request->get_param('lines')
            );

            return new WP_REST_Response($this->updateBudget->execute($command)->toArray(), 200);
        } catch (BudgetAccessDeniedException $exception) {
            return new WP_Error('stageart_budget_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (BudgetNotFoundException $exception) {
            return new WP_Error('stageart_budget_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_budget_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function activate(WP_REST_Request $request)
    {
        try {
            $command = new ActivateBudgetCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->activateBudget->execute($command)->toArray(), 200);
        } catch (BudgetAccessDeniedException $exception) {
            return new WP_Error('stageart_budget_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (BudgetNotFoundException $exception) {
            return new WP_Error('stageart_budget_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_budget_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
