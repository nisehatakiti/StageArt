<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\MemberPerformanceSummary\GetMemberPerformanceSummaryQuery;
use StageArt\Application\MemberPerformanceSummary\GetMemberPerformanceSummaryUseCase;
use StageArt\Application\MemberPerformanceSummary\MemberPerformanceSummaryAccessDeniedException;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Phase 5 (Production運営UI §9): the Member Performance Summary's single
 * read-only endpoint.
 */
final class MemberPerformanceSummaryRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetMemberPerformanceSummaryUseCase $getSummary;

    public function __construct(GetMemberPerformanceSummaryUseCase $getSummary)
    {
        $this->getSummary = $getSummary;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/member-performance-summary', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'summary'],
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
    public function summary(WP_REST_Request $request)
    {
        try {
            $query = new GetMemberPerformanceSummaryQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getSummary->execute($query)->toArray(), 200);
        } catch (MemberPerformanceSummaryAccessDeniedException $exception) {
            return new WP_Error('stageart_member_performance_summary_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
