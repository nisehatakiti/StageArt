<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\ProductionAccounting\GetProductionAccountingDetailUseCase;
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
 *
 * GET /productions/{id}/budget-actuals - 公演会計の費目別の予算・実績と
 * 取引明細 (OrganizationAccountingAndProductionCostPolicy.md §3).
 */
final class ProductionAccountingRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetProductionAccountingSummaryUseCase $getSummary;
    private ?GetProductionAccountingDetailUseCase $getDetail;

    public function __construct(GetProductionAccountingSummaryUseCase $getSummary, ?GetProductionAccountingDetailUseCase $getDetail = null)
    {
        $this->getSummary = $getSummary;
        $this->getDetail = $getDetail;
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

        if ($this->getDetail !== null) {
            register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/budget-actuals', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_detail'],
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

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get_detail(WP_REST_Request $request)
    {
        try {
            $query = new GetProductionAccountingSummaryQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getDetail->execute($query)->toArray(), 200);
        } catch (ProductionAccountingAccessDeniedException $exception) {
            return new WP_Error('stageart_production_accounting_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
