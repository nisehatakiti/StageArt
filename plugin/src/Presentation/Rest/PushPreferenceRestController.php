<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Notification\GetPushPreferenceQuery;
use StageArt\Application\Notification\GetPushPreferenceUseCase;
use StageArt\Application\Notification\NotificationAccessDeniedException;
use StageArt\Application\Notification\UpdatePushPreferenceCommand;
use StageArt\Application\Notification\UpdatePushPreferenceUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Deliberately has no {id} parameter anywhere in its routes: Push
 * Preference is always resolved from the requester's own
 * get_current_user_id(), which is what makes "本人のみ" (Phase 4
 * instruction §25) structurally true rather than merely
 * Authorization-enforced (see UpdatePushPreferenceUseCase's docblock).
 */
final class PushPreferenceRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetPushPreferenceUseCase $getPreference;
    private UpdatePushPreferenceUseCase $updatePreference;

    public function __construct(GetPushPreferenceUseCase $getPreference, UpdatePushPreferenceUseCase $updatePreference)
    {
        $this->getPreference = $getPreference;
        $this->updatePreference = $updatePreference;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/me/push-preference', [
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
    }

    public function require_login(): bool
    {
        return is_user_logged_in();
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetPushPreferenceQuery(get_current_user_id());

            return new WP_REST_Response($this->getPreference->execute($query)->toArray(), 200);
        } catch (NotificationAccessDeniedException $exception) {
            return new WP_Error('stageart_push_preference_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdatePushPreferenceCommand(get_current_user_id(), (bool) $request->get_param('enabled'));

            return new WP_REST_Response($this->updatePreference->execute($command)->toArray(), 200);
        } catch (NotificationAccessDeniedException $exception) {
            return new WP_Error('stageart_push_preference_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }
}
