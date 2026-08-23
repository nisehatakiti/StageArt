<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Notification\ListNotificationsForProductionQuery;
use StageArt\Application\Notification\ListNotificationsForProductionUseCase;
use StageArt\Application\Notification\MarkNotificationReadCommand;
use StageArt\Application\Notification\MarkNotificationReadUseCase;
use StageArt\Application\Notification\NotificationAccessDeniedException;
use StageArt\Application\Notification\NotificationNotFoundException;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * List is read-only, matching Notification.md's "取得方法": every
 * Production Member sees the identical Notification Fact list - no
 * per-Role query param, same Shared Visibility Principle already
 * applied to TimetableRestController's Production Schedule route.
 *
 * Phase 7.0 adds the one write operation NotificationPolicy.md's
 * "未読 / 既読" requires - marking a Notification read for the caller.
 */
final class NotificationRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private ListNotificationsForProductionUseCase $listNotifications;
    private MarkNotificationReadUseCase $markRead;

    public function __construct(ListNotificationsForProductionUseCase $listNotifications, MarkNotificationReadUseCase $markRead)
    {
        $this->listNotifications = $listNotifications;
        $this->markRead = $markRead;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/notifications', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listForProduction'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/notifications/(?P<id>[^/]+)/read', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'markRead'],
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
    public function listForProduction(WP_REST_Request $request)
    {
        try {
            $query = new ListNotificationsForProductionQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listNotifications->execute($query)),
                200
            );
        } catch (NotificationAccessDeniedException $exception) {
            return new WP_Error('stageart_notification_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_notification_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function markRead(WP_REST_Request $request)
    {
        try {
            $notificationId = (string) $request->get_param('id');
            $command = new MarkNotificationReadCommand($notificationId, get_current_user_id());

            $this->markRead->execute($command);

            return new WP_REST_Response(['id' => $notificationId, 'is_read' => true], 200);
        } catch (NotificationAccessDeniedException $exception) {
            return new WP_Error('stageart_notification_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (NotificationNotFoundException $exception) {
            return new WP_Error('stageart_notification_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_notification_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
