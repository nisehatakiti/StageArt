<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Notification\ListMyNotificationsQuery;
use StageArt\Application\Notification\ListMyNotificationsUseCase;
use StageArt\Application\Notification\ListNotificationsForProductionQuery;
use StageArt\Application\Notification\ListNotificationsForProductionUseCase;
use StageArt\Application\Notification\MarkMyNotificationReadCommand;
use StageArt\Application\Notification\MarkMyNotificationReadUseCase;
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
 *
 * Notification基盤実装 phase: adds the personal `/me/notifications` pair
 * (list + mark-read) for the new generic, per-recipient `Notification`
 * Fact type (Rehearsal Cancel/Reminder today) - kept on this same
 * Controller rather than a new one, since it is the same "Notification"
 * concern, just a second Fact type with a different (personal, not
 * Production-shared) visibility model. Route paths are distinct
 * (`/me/notifications...` vs `/notifications/{id}/read`) specifically so
 * the two Fact types' id namespaces never collide on the same route.
 */
final class NotificationRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private ListNotificationsForProductionUseCase $listNotifications;
    private MarkNotificationReadUseCase $markRead;
    private ListMyNotificationsUseCase $listMyNotifications;
    private MarkMyNotificationReadUseCase $markMyNotificationRead;

    public function __construct(
        ListNotificationsForProductionUseCase $listNotifications,
        MarkNotificationReadUseCase $markRead,
        ListMyNotificationsUseCase $listMyNotifications,
        MarkMyNotificationReadUseCase $markMyNotificationRead
    ) {
        $this->listNotifications = $listNotifications;
        $this->markRead = $markRead;
        $this->listMyNotifications = $listMyNotifications;
        $this->markMyNotificationRead = $markMyNotificationRead;
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

        register_rest_route(self::API_NAMESPACE, '/me/notifications', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listMine'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/me/notifications/(?P<id>[^/]+)/read', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'markMineRead'],
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

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listMine(WP_REST_Request $request)
    {
        try {
            $query = new ListMyNotificationsQuery(get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listMyNotifications->execute($query)),
                200
            );
        } catch (NotificationAccessDeniedException $exception) {
            return new WP_Error('stageart_notification_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function markMineRead(WP_REST_Request $request)
    {
        try {
            $notificationId = (string) $request->get_param('id');
            $command = new MarkMyNotificationReadCommand($notificationId, get_current_user_id());

            $this->markMyNotificationRead->execute($command);

            return new WP_REST_Response(['id' => $notificationId, 'is_read' => true], 200);
        } catch (NotificationAccessDeniedException $exception) {
            return new WP_Error('stageart_notification_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (NotificationNotFoundException $exception) {
            return new WP_Error('stageart_notification_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
