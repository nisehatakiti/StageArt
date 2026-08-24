<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ActivateProductionCommand;
use StageArt\Application\Production\ActivateProductionUseCase;
use StageArt\Application\Production\ArchiveProductionCommand;
use StageArt\Application\Production\ArchiveProductionUseCase;
use StageArt\Application\Production\CancelProductionCommand;
use StageArt\Application\Production\CancelProductionUseCase;
use StageArt\Application\Production\ChangePrimaryManagerCommand;
use StageArt\Application\Production\ChangePrimaryManagerUseCase;
use StageArt\Application\Production\CompleteProductionCommand;
use StageArt\Application\Production\CompleteProductionUseCase;
use StageArt\Application\Production\CreateProductionCommand;
use StageArt\Application\Production\CreateProductionUseCase;
use StageArt\Application\Production\GetProductionQuery;
use StageArt\Application\Production\GetProductionUseCase;
use StageArt\Application\Production\GetPublicProductionBySlugQuery;
use StageArt\Application\Production\GetPublicProductionBySlugUseCase;
use StageArt\Application\Production\ListProductionsForPersonQuery;
use StageArt\Application\Production\ListProductionsUseCase;
use StageArt\Application\Production\PrimaryManagerNotEligibleException;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Production\ProductionSlugAlreadyTakenException;
use StageArt\Application\Production\StartProductionPlanningCommand;
use StageArt\Application\Production\StartProductionPlanningUseCase;
use StageArt\Application\Production\UpdateProductionCommand;
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Application\Project\ProjectNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * permission_callback only checks authentication; the actual Production
 * Scope decision (PrimaryManager / ProductionDelegate) happens inside
 * each Use Case via ProductionAuthorizationService and is mapped to a
 * 403 here, same pattern as OrganizationRestController.
 */
final class ProductionRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateProductionUseCase $createProduction;
    private GetProductionUseCase $getProduction;
    private GetPublicProductionBySlugUseCase $getPublicProductionBySlug;
    private ListProductionsUseCase $listProductions;
    private UpdateProductionUseCase $updateProduction;
    private ChangePrimaryManagerUseCase $changePrimaryManager;
    private StartProductionPlanningUseCase $startPlanning;
    private ActivateProductionUseCase $activateProduction;
    private CompleteProductionUseCase $completeProduction;
    private ArchiveProductionUseCase $archiveProduction;
    private CancelProductionUseCase $cancelProduction;

    public function __construct(
        CreateProductionUseCase $createProduction,
        GetProductionUseCase $getProduction,
        GetPublicProductionBySlugUseCase $getPublicProductionBySlug,
        ListProductionsUseCase $listProductions,
        UpdateProductionUseCase $updateProduction,
        ChangePrimaryManagerUseCase $changePrimaryManager,
        StartProductionPlanningUseCase $startPlanning,
        ActivateProductionUseCase $activateProduction,
        CompleteProductionUseCase $completeProduction,
        ArchiveProductionUseCase $archiveProduction,
        CancelProductionUseCase $cancelProduction
    ) {
        $this->createProduction = $createProduction;
        $this->getProduction = $getProduction;
        $this->getPublicProductionBySlug = $getPublicProductionBySlug;
        $this->listProductions = $listProductions;
        $this->updateProduction = $updateProduction;
        $this->changePrimaryManager = $changePrimaryManager;
        $this->startPlanning = $startPlanning;
        $this->activateProduction = $activateProduction;
        $this->completeProduction = $completeProduction;
        $this->archiveProduction = $archiveProduction;
        $this->cancelProduction = $cancelProduction;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions', [
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

        register_rest_route(self::API_NAMESPACE, '/productions/by-slug/(?P<slug>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getBySlug'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)', [
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

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/primary-manager', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'changePrimaryManager'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        // Phase 6.1 Lifecycle Actions - see ProductionLifecycle.md's
        // Action/GO model. Action names are derived from each transition's
        // target Status (Blueprint names target Status labels, not literal
        // Action verbs - see Production::startPlanning()'s docblock).
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/start-planning', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'startPlanning'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/activate', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'activate'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/complete', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'complete'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/archive', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'archive'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/cancel', [
            [
                'methods' => 'PATCH',
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
     * @return WP_REST_Response
     */
    public function list(WP_REST_Request $request)
    {
        $results = $this->listProductions->execute(
            new ListProductionsForPersonQuery(get_current_user_id())
        );

        return new WP_REST_Response(
            array_map(static fn ($result) => $result->toArray(), $results),
            200
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateProductionCommand(
                get_current_user_id(),
                (string) $request->get_param('project_id'),
                (string) $request->get_param('name'),
                (string) $request->get_param('slug'),
                (string) $request->get_param('primary_manager_person_id'),
                $this->stringOrNull($request->get_param('title_heading'))
            );

            return new WP_REST_Response($this->createProduction->execute($command)->toArray(), 201);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProjectNotFoundException $exception) {
            return new WP_Error('stageart_project_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (PrimaryManagerNotEligibleException $exception) {
            return new WP_Error('stageart_primary_manager_not_eligible', $exception->getMessage(), ['status' => 422]);
        } catch (ProductionSlugAlreadyTakenException $exception) {
            return new WP_Error('stageart_production_slug_taken', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetProductionQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getProduction->execute($query)->toArray(), 200);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getBySlug(WP_REST_Request $request)
    {
        try {
            $query = new GetPublicProductionBySlugQuery((string) $request->get_param('slug'));

            return new WP_REST_Response($this->getPublicProductionBySlug->execute($query)->toArray(), 200);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * Phase 6.1: `status` is no longer an updatable field here -
     * ProductionLifecycle.md's Action-based model moved Status changes to
     * the dedicated Lifecycle Action endpoints below. A request that
     * still sends `status` is rejected with 422 (rather than silently
     * ignored) so a stale caller gets an explicit, diagnosable error
     * instead of a silently-no-op'd Status change.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        if ($request->get_param('status') !== null) {
            return new WP_Error(
                'stageart_production_status_not_updatable_via_put',
                'Production Status can no longer be changed via PUT. Use the Lifecycle Action endpoints '
                    . '(/start-planning, /activate, /complete, /archive, /cancel) instead.',
                ['status' => 422]
            );
        }

        try {
            $slugParam = $request->get_param('slug');
            $publishedParam = $request->get_param('published');

            $command = new UpdateProductionCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('name'),
                $this->stringOrNull($request->get_param('title_heading')),
                $this->stringOrNull($slugParam),
                $publishedParam === null ? null : (bool) $publishedParam
            );

            return new WP_REST_Response($this->updateProduction->execute($command)->toArray(), 200);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionSlugAlreadyTakenException $exception) {
            return new WP_Error('stageart_production_slug_taken', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function startPlanning(WP_REST_Request $request)
    {
        try {
            $command = new StartProductionPlanningCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->startPlanning->execute($command)->toArray(), 200);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid_transition', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function activate(WP_REST_Request $request)
    {
        try {
            $command = new ActivateProductionCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->activateProduction->execute($command)->toArray(), 200);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid_transition', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function complete(WP_REST_Request $request)
    {
        try {
            $command = new CompleteProductionCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->completeProduction->execute($command)->toArray(), 200);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid_transition', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function archive(WP_REST_Request $request)
    {
        try {
            $command = new ArchiveProductionCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->archiveProduction->execute($command)->toArray(), 200);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid_transition', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function cancel(WP_REST_Request $request)
    {
        try {
            $command = new CancelProductionCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->cancelProduction->execute($command)->toArray(), 200);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid_transition', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function changePrimaryManager(WP_REST_Request $request)
    {
        try {
            $command = new ChangePrimaryManagerCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('new_primary_manager_person_id')
            );

            return new WP_REST_Response($this->changePrimaryManager->execute($command)->toArray(), 200);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProjectNotFoundException $exception) {
            return new WP_Error('stageart_project_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (PrimaryManagerNotEligibleException $exception) {
            return new WP_Error('stageart_primary_manager_not_eligible', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    private function stringOrNull($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
