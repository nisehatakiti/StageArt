<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Notification\GetNotificationEmailSettingsQuery;
use StageArt\Application\Notification\GetNotificationEmailSettingsUseCase;
use StageArt\Application\Notification\InvalidNotificationEmailChangeTokenException;
use StageArt\Application\Notification\InvalidNotificationEmailException;
use StageArt\Application\Notification\NotificationAccessDeniedException;
use StageArt\Application\Notification\RequestNotificationEmailChangeCommand;
use StageArt\Application\Notification\RequestNotificationEmailChangeUseCase;
use StageArt\Application\Notification\VerifyNotificationEmailChangeCommand;
use StageArt\Application\Notification\VerifyNotificationEmailChangeUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * 通知用Email確認・変更機能 §15/§16: the current-status and change-
 * request routes have no {id} parameter - exactly like
 * `PushPreferenceRestController`'s own `/me/push-preference`, the
 * target Person is always resolved from the requester's own
 * get_current_user_id(), which is what makes "本人のみ" structurally
 * true (see GetNotificationEmailSettingsUseCase's own docblock). The
 * verify route is the one deliberate exception - public/token-only,
 * matching AuthenticationRestController's `/auth/email/verify` (see
 * VerifyNotificationEmailChangeUseCase's own docblock for why).
 */
final class NotificationEmailRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetNotificationEmailSettingsUseCase $getSettings;
    private RequestNotificationEmailChangeUseCase $requestChange;
    private VerifyNotificationEmailChangeUseCase $verifyChange;

    public function __construct(
        GetNotificationEmailSettingsUseCase $getSettings,
        RequestNotificationEmailChangeUseCase $requestChange,
        VerifyNotificationEmailChangeUseCase $verifyChange
    ) {
        $this->getSettings = $getSettings;
        $this->requestChange = $requestChange;
        $this->verifyChange = $verifyChange;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/me/notification-email', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/me/notification-email/change-request', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'requestChange'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/notification-email/verify', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'verify'],
                'permission_callback' => '__return_true',
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
            $query = new GetNotificationEmailSettingsQuery(get_current_user_id());

            return new WP_REST_Response($this->getSettings->execute($query)->toArray(), 200);
        } catch (NotificationAccessDeniedException $exception) {
            return new WP_Error('stageart_notification_email_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function requestChange(WP_REST_Request $request)
    {
        try {
            $command = new RequestNotificationEmailChangeCommand(get_current_user_id(), (string) $request->get_param('email'));

            return new WP_REST_Response($this->requestChange->execute($command)->toArray(), 200);
        } catch (NotificationAccessDeniedException $exception) {
            return new WP_Error('stageart_notification_email_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (InvalidNotificationEmailException $exception) {
            return new WP_Error('stageart_notification_email_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function verify(WP_REST_Request $request)
    {
        try {
            $command = new VerifyNotificationEmailChangeCommand((string) $request->get_param('token'));
            $this->verifyChange->execute($command);

            return new WP_REST_Response(['success' => true], 200);
        } catch (InvalidNotificationEmailChangeTokenException $exception) {
            return new WP_Error('stageart_invalid_notification_email_change_token', $exception->getMessage(), ['status' => 401]);
        }
    }
}
