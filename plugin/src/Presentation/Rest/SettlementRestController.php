<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Settlement\CancelProductionMemberSettlementCommand;
use StageArt\Application\Settlement\CancelProductionMemberSettlementUseCase;
use StageArt\Application\Settlement\GetProductionSettlementSummaryQuery;
use StageArt\Application\Settlement\GetProductionSettlementSummaryUseCase;
use StageArt\Application\Settlement\NothingToCancelException;
use StageArt\Application\Settlement\NothingToSettleException;
use StageArt\Application\Settlement\SettleProductionMemberCommand;
use StageArt\Application\Settlement\SettleProductionMemberUseCase;
use StageArt\Application\Settlement\SettlementAccessDeniedException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ProductionSettlementScreen.md (Chapter 29) + Phase 5 §7: the "精算"
 * screen's own three endpoints - the per-member summary Read Model,
 * settling one member, and cancelling that member's most recent
 * settlement (the "精算済み" checkbox unchecked).
 */
final class SettlementRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetProductionSettlementSummaryUseCase $getSummary;
    private SettleProductionMemberUseCase $settleMember;
    private CancelProductionMemberSettlementUseCase $cancelSettlement;

    public function __construct(
        GetProductionSettlementSummaryUseCase $getSummary,
        SettleProductionMemberUseCase $settleMember,
        CancelProductionMemberSettlementUseCase $cancelSettlement
    ) {
        $this->getSummary = $getSummary;
        $this->settleMember = $settleMember;
        $this->cancelSettlement = $cancelSettlement;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/settlement', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'summary'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/settlement/members/(?P<person_id>[^/]+)/settle', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'settle'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/settlement/members/(?P<person_id>[^/]+)/cancel-settlement', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'cancelSettlement'],
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
            $query = new GetProductionSettlementSummaryQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getSummary->execute($query)->toArray(), 200);
        } catch (SettlementAccessDeniedException $exception) {
            return new WP_Error('stageart_settlement_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function settle(WP_REST_Request $request)
    {
        try {
            $command = new SettleProductionMemberCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('person_id'),
                get_current_user_id()
            );

            $this->settleMember->execute($command);

            return new WP_REST_Response(['status' => 'ok'], 200);
        } catch (SettlementAccessDeniedException $exception) {
            return new WP_Error('stageart_settlement_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (NothingToSettleException $exception) {
            return new WP_Error('stageart_nothing_to_settle', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function cancelSettlement(WP_REST_Request $request)
    {
        try {
            $command = new CancelProductionMemberSettlementCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('person_id'),
                get_current_user_id()
            );

            $this->cancelSettlement->execute($command);

            return new WP_REST_Response(['status' => 'ok'], 200);
        } catch (SettlementAccessDeniedException $exception) {
            return new WP_Error('stageart_settlement_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (NothingToCancelException $exception) {
            return new WP_Error('stageart_nothing_to_cancel', $exception->getMessage(), ['status' => 422]);
        }
    }
}
