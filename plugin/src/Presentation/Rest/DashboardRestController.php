<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Dashboard\DashboardAccessDeniedException;
use StageArt\Application\Dashboard\GetMyDashboardQuery;
use StageArt\Application\Dashboard\GetMyDashboardUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Phase 7.3: `/me/dashboard`, mirroring PushPreferenceRestController's
 * `/me/push-preference` precedent - no {id} parameter, always resolved
 * from the requester's own get_current_user_id(), so "only my own data"
 * is structural, not merely Authorization-enforced (see this Phase's
 * report §11/§12). This is a Person-centric Read Model endpoint, not a
 * commitment that any particular Client screen is named "Dashboard" -
 * see GetMyDashboardUseCase's docblock and this Phase's report §39.
 */
final class DashboardRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetMyDashboardUseCase $getMyDashboard;

    public function __construct(GetMyDashboardUseCase $getMyDashboard)
    {
        $this->getMyDashboard = $getMyDashboard;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/me/dashboard', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
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
            $query = new GetMyDashboardQuery(get_current_user_id());

            return new WP_REST_Response($this->getMyDashboard->execute($query)->toArray(), 200);
        } catch (DashboardAccessDeniedException $exception) {
            return new WP_Error('stageart_dashboard_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }
}
