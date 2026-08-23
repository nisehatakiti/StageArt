<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryQuery;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryUseCase;
use StageArt\Application\ProductionAccounting\ProductionAccountingAccessDeniedException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Mobile Phase 5.4's "会計概要" - GET /productions/{id}/accounting is the
 * one endpoint this Phase confirms and finalizes for that screen (see
 * the Phase 6.0 report's "Mobileとの API整合性" section for the exact
 * shape match).
 */
final class ProductionAccountingRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetProductionAccountingSummaryUseCase $getSummary;

    public function __construct(GetProductionAccountingSummaryUseCase $getSummary)
    {
        $this->getSummary = $getSummary;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/accounting', [
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
            $query = new GetProductionAccountingSummaryQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getSummary->execute($query)->toArray(), 200);
        } catch (ProductionAccountingAccessDeniedException $exception) {
            return new WP_Error('stageart_production_accounting_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
