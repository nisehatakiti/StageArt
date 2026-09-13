<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Performance\CancelPerformanceCommand;
use StageArt\Application\Performance\CancelPerformanceUseCase;
use StageArt\Application\Performance\CreatePerformanceCommand;
use StageArt\Application\Performance\CreatePerformanceUseCase;
use StageArt\Application\Performance\GetPerformanceQuery;
use StageArt\Application\Performance\GetPerformanceUseCase;
use StageArt\Application\Performance\ListPerformancesForProductionQuery;
use StageArt\Application\Performance\ListPerformancesUseCase;
use StageArt\Application\Performance\ListPublicPerformancesQuery;
use StageArt\Application\Performance\ListPublicPerformancesUseCase;
use StageArt\Application\Performance\PerformanceAccessDeniedException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Performance\UpdatePerformanceCommand;
use StageArt\Application\Performance\UpdatePerformanceUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Phase 2 Performance基盤 §21: `/productions/{id}/performances`
 * (list/create) and `/performances/{id}` (get/update) plus
 * `/performances/{id}/cancel`, matching Rehearsal's own URL family shape
 * exactly (§21 - "新しいAPI設計思想を独自に導入しない").
 */
final class PerformanceRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreatePerformanceUseCase $createPerformance;
    private GetPerformanceUseCase $getPerformance;
    private ListPerformancesUseCase $listPerformances;
    private UpdatePerformanceUseCase $updatePerformance;
    private CancelPerformanceUseCase $cancelPerformance;
    private ListPublicPerformancesUseCase $listPublicPerformances;

    public function __construct(
        CreatePerformanceUseCase $createPerformance,
        GetPerformanceUseCase $getPerformance,
        ListPerformancesUseCase $listPerformances,
        UpdatePerformanceUseCase $updatePerformance,
        CancelPerformanceUseCase $cancelPerformance,
        ListPublicPerformancesUseCase $listPublicPerformances
    ) {
        $this->createPerformance = $createPerformance;
        $this->getPerformance = $getPerformance;
        $this->listPerformances = $listPerformances;
        $this->updatePerformance = $updatePerformance;
        $this->cancelPerformance = $cancelPerformance;
        $this->listPublicPerformances = $listPublicPerformances;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/performances', [
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

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/public-performances', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listPublic'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)', [
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

        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/cancel', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'cancel'],
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
            $query = new ListPerformancesForProductionQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listPerformances->execute($query)),
                200
            );
        } catch (PerformanceAccessDeniedException $exception) {
            return new WP_Error('stageart_performance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_performance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listPublic(WP_REST_Request $request)
    {
        try {
            $query = new ListPublicPerformancesQuery((string) $request->get_param('id'));

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listPublicPerformances->execute($query)),
                200
            );
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
            $command = new CreatePerformanceCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('performance_date'),
                (string) $request->get_param('start_time'),
                $this->stringOrNull($request->get_param('end_time')),
                $this->intOrNull($request->get_param('capacity')),
                $this->stringOrNull($request->get_param('remarks')),
                $this->stringOrNull($request->get_param('symbol'))
            );

            return new WP_REST_Response($this->createPerformance->execute($command)->toArray(), 201);
        } catch (PerformanceAccessDeniedException $exception) {
            return new WP_Error('stageart_performance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_performance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetPerformanceQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getPerformance->execute($query)->toArray(), 200);
        } catch (PerformanceAccessDeniedException $exception) {
            return new WP_Error('stageart_performance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (PerformanceNotFoundException $exception) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_performance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdatePerformanceCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('performance_date'),
                (string) $request->get_param('start_time'),
                $this->stringOrNull($request->get_param('end_time')),
                (int) $request->get_param('capacity'),
                $this->stringOrNull($request->get_param('remarks')),
                $this->stringOrNull($request->get_param('symbol')),
                $this->stringOrNull($request->get_param('status'))
            );

            return new WP_REST_Response($this->updatePerformance->execute($command)->toArray(), 200);
        } catch (PerformanceAccessDeniedException $exception) {
            return new WP_Error('stageart_performance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (PerformanceNotFoundException $exception) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_performance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function cancel(WP_REST_Request $request)
    {
        try {
            $command = new CancelPerformanceCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->cancelPerformance->execute($command)->toArray(), 200);
        } catch (PerformanceAccessDeniedException $exception) {
            return new WP_Error('stageart_performance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (PerformanceNotFoundException $exception) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_performance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function intOrNull($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
