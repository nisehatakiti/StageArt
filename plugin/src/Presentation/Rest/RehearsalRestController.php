<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\ActivateRehearsalCommand;
use StageArt\Application\Rehearsal\ActivateRehearsalUseCase;
use StageArt\Application\Rehearsal\CancelRehearsalCommand;
use StageArt\Application\Rehearsal\CancelRehearsalUseCase;
use StageArt\Application\Rehearsal\CompleteRehearsalCommand;
use StageArt\Application\Rehearsal\CompleteRehearsalUseCase;
use StageArt\Application\Rehearsal\ConfirmRehearsalCommand;
use StageArt\Application\Rehearsal\ConfirmRehearsalUseCase;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\GetRehearsalQuery;
use StageArt\Application\Rehearsal\GetRehearsalUseCase;
use StageArt\Application\Rehearsal\ListRehearsalsForProductionQuery;
use StageArt\Application\Rehearsal\ListRehearsalsUseCase;
use StageArt\Application\Rehearsal\RehearsalAccessDeniedException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Rehearsal\UpdateRehearsalCommand;
use StageArt\Application\Rehearsal\UpdateRehearsalUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RehearsalRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateRehearsalUseCase $createRehearsal;
    private GetRehearsalUseCase $getRehearsal;
    private ListRehearsalsUseCase $listRehearsals;
    private UpdateRehearsalUseCase $updateRehearsal;
    private ConfirmRehearsalUseCase $confirmRehearsal;
    private ActivateRehearsalUseCase $activateRehearsal;
    private CompleteRehearsalUseCase $completeRehearsal;
    private CancelRehearsalUseCase $cancelRehearsal;

    public function __construct(
        CreateRehearsalUseCase $createRehearsal,
        GetRehearsalUseCase $getRehearsal,
        ListRehearsalsUseCase $listRehearsals,
        UpdateRehearsalUseCase $updateRehearsal,
        ConfirmRehearsalUseCase $confirmRehearsal,
        ActivateRehearsalUseCase $activateRehearsal,
        CompleteRehearsalUseCase $completeRehearsal,
        CancelRehearsalUseCase $cancelRehearsal
    ) {
        $this->createRehearsal = $createRehearsal;
        $this->getRehearsal = $getRehearsal;
        $this->listRehearsals = $listRehearsals;
        $this->updateRehearsal = $updateRehearsal;
        $this->confirmRehearsal = $confirmRehearsal;
        $this->activateRehearsal = $activateRehearsal;
        $this->completeRehearsal = $completeRehearsal;
        $this->cancelRehearsal = $cancelRehearsal;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/rehearsals', [
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

        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)', [
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

        foreach (['confirm' => 'confirm', 'activate' => 'activate', 'complete' => 'complete', 'cancel' => 'cancel'] as $route => $handler) {
            register_rest_route(self::API_NAMESPACE, "/rehearsals/(?P<id>[^/]+)/{$route}", [
                [
                    'methods' => 'POST',
                    'callback' => [$this, $handler],
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
            $query = new ListRehearsalsForProductionQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listRehearsals->execute($query)),
                200
            );
        } catch (RehearsalAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateRehearsalCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                $this->stringOrNull($request->get_param('title')),
                $this->stringOrNull($request->get_param('description')),
                $this->stringOrNull($request->get_param('start_date_time')),
                $this->stringOrNull($request->get_param('end_date_time')),
                $this->stringOrNull($request->get_param('timezone')),
                $this->stringOrNull($request->get_param('location'))
            );

            return new WP_REST_Response($this->createRehearsal->execute($command)->toArray(), 201);
        } catch (RehearsalAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetRehearsalQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getRehearsal->execute($query)->toArray(), 200);
        } catch (RehearsalAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateRehearsalCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                $this->stringOrNull($request->get_param('title')),
                $this->stringOrNull($request->get_param('description')),
                $this->stringOrNull($request->get_param('start_date_time')),
                $this->stringOrNull($request->get_param('end_date_time')),
                $this->stringOrNull($request->get_param('timezone')),
                $this->stringOrNull($request->get_param('location'))
            );

            return new WP_REST_Response($this->updateRehearsal->execute($command)->toArray(), 200);
        } catch (RehearsalAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function confirm(WP_REST_Request $request)
    {
        try {
            $command = new ConfirmRehearsalCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->confirmRehearsal->execute($command)->toArray(), 200);
        } catch (RehearsalAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function activate(WP_REST_Request $request)
    {
        try {
            $command = new ActivateRehearsalCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->activateRehearsal->execute($command)->toArray(), 200);
        } catch (RehearsalAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function complete(WP_REST_Request $request)
    {
        try {
            $command = new CompleteRehearsalCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->completeRehearsal->execute($command)->toArray(), 200);
        } catch (RehearsalAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function cancel(WP_REST_Request $request)
    {
        try {
            $command = new CancelRehearsalCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->cancelRehearsal->execute($command)->toArray(), 200);
        } catch (RehearsalAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
