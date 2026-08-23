<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesQuery;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesUseCase;
use StageArt\Application\ProductionDelegate\ProductionDelegateAccessDeniedException;
use StageArt\Application\ProductionDelegate\ProductionDelegateAlreadyExistsException;
use StageArt\Application\ProductionDelegate\ProductionDelegateNotFoundException;
use StageArt\Application\ProductionDelegate\ProductionDelegateTargetNotEligibleException;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ProductionDelegateRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateProductionDelegateUseCase $createDelegate;
    private ListProductionDelegatesUseCase $listDelegates;
    private UpdateProductionDelegateUseCase $updateDelegate;
    private DeleteProductionDelegateUseCase $deleteDelegate;

    public function __construct(
        CreateProductionDelegateUseCase $createDelegate,
        ListProductionDelegatesUseCase $listDelegates,
        UpdateProductionDelegateUseCase $updateDelegate,
        DeleteProductionDelegateUseCase $deleteDelegate
    ) {
        $this->createDelegate = $createDelegate;
        $this->listDelegates = $listDelegates;
        $this->updateDelegate = $updateDelegate;
        $this->deleteDelegate = $deleteDelegate;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/delegates', [
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

        register_rest_route(self::API_NAMESPACE, '/production-delegates/(?P<id>[^/]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete'],
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
            $query = new ListProductionDelegatesQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listDelegates->execute($query)),
                200
            );
        } catch (ProductionDelegateAccessDeniedException $exception) {
            return new WP_Error('stageart_production_delegate_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_delegate_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateProductionDelegateCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('person_id'),
                (string) $request->get_param('role')
            );

            return new WP_REST_Response($this->createDelegate->execute($command)->toArray(), 201);
        } catch (ProductionDelegateAccessDeniedException $exception) {
            return new WP_Error('stageart_production_delegate_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionDelegateTargetNotEligibleException $exception) {
            return new WP_Error('stageart_production_delegate_target_not_eligible', $exception->getMessage(), ['status' => 422]);
        } catch (ProductionDelegateAlreadyExistsException $exception) {
            return new WP_Error('stageart_production_delegate_already_exists', $exception->getMessage(), ['status' => 409]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_delegate_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateProductionDelegateCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('role'),
                (string) $request->get_param('status')
            );

            return new WP_REST_Response($this->updateDelegate->execute($command)->toArray(), 200);
        } catch (ProductionDelegateAccessDeniedException $exception) {
            return new WP_Error('stageart_production_delegate_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionDelegateNotFoundException $exception) {
            return new WP_Error('stageart_production_delegate_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_delegate_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request)
    {
        try {
            $command = new DeleteProductionDelegateCommand((string) $request->get_param('id'), get_current_user_id());
            $this->deleteDelegate->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (ProductionDelegateAccessDeniedException $exception) {
            return new WP_Error('stageart_production_delegate_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionDelegateNotFoundException $exception) {
            return new WP_Error('stageart_production_delegate_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
