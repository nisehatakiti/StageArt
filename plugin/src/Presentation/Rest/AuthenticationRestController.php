<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Authentication\AuthenticateWithEmailCommand;
use StageArt\Application\Authentication\AuthenticateWithEmailUseCase;
use StageArt\Application\Authentication\AuthenticateWithGoogleCommand;
use StageArt\Application\Authentication\AuthenticateWithGoogleUseCase;
use StageArt\Application\Authentication\InvalidCredentialsException;
use StageArt\Application\Authentication\InvalidEmailVerificationTokenException;
use StageArt\Application\Authentication\InvalidGoogleIdTokenException;
use StageArt\Application\Authentication\InvalidPasswordResetTokenException;
use StageArt\Application\Authentication\InvalidRefreshTokenException;
use StageArt\Application\Authentication\LogoutCommand;
use StageArt\Application\Authentication\LogoutUseCase;
use StageArt\Application\Authentication\RefreshAccessTokenCommand;
use StageArt\Application\Authentication\RefreshAccessTokenUseCase;
use StageArt\Application\Authentication\RegisterWithEmailCommand;
use StageArt\Application\Authentication\RegisterWithEmailUseCase;
use StageArt\Application\Authentication\RequestPasswordResetCommand;
use StageArt\Application\Authentication\RequestPasswordResetUseCase;
use StageArt\Application\Authentication\ResetPasswordCommand;
use StageArt\Application\Authentication\ResetPasswordUseCase;
use StageArt\Application\Authentication\VerifyEmailCommand;
use StageArt\Application\Authentication\VerifyEmailUseCase;
use StageArt\Application\UserAccount\EmailAlreadyInUseException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The only public (no prior WordPress authentication required) routes
 * in this Backend - by design, since establishing a session is exactly
 * what these do. Each Use Case performs its own robust verification
 * (Google ID Token signature/issuer/audience, password hash comparison,
 * or Refresh/Reset/Verification Token hash/expiry/consumption) before
 * trusting anything in the request body - see this Phase's design report
 * §1.
 *
 * Email+Password Phase (StageArt Authentication): /auth/email/register
 * and /auth/email/login are the Email+Password mirrors of /auth/google's
 * new-account and existing-account paths, reusing the identical
 * AuthenticationResult shape and Access/Refresh Token issuance - a
 * client cannot distinguish which provider a session came from by
 * inspecting the response shape alone.
 */
final class AuthenticationRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private AuthenticateWithGoogleUseCase $authenticateWithGoogle;
    private RegisterWithEmailUseCase $registerWithEmail;
    private AuthenticateWithEmailUseCase $authenticateWithEmail;
    private RefreshAccessTokenUseCase $refreshAccessToken;
    private LogoutUseCase $logout;
    private RequestPasswordResetUseCase $requestPasswordReset;
    private ResetPasswordUseCase $resetPassword;
    private VerifyEmailUseCase $verifyEmail;

    public function __construct(
        AuthenticateWithGoogleUseCase $authenticateWithGoogle,
        RegisterWithEmailUseCase $registerWithEmail,
        AuthenticateWithEmailUseCase $authenticateWithEmail,
        RefreshAccessTokenUseCase $refreshAccessToken,
        LogoutUseCase $logout,
        RequestPasswordResetUseCase $requestPasswordReset,
        ResetPasswordUseCase $resetPassword,
        VerifyEmailUseCase $verifyEmail
    ) {
        $this->authenticateWithGoogle = $authenticateWithGoogle;
        $this->registerWithEmail = $registerWithEmail;
        $this->authenticateWithEmail = $authenticateWithEmail;
        $this->refreshAccessToken = $refreshAccessToken;
        $this->logout = $logout;
        $this->requestPasswordReset = $requestPasswordReset;
        $this->resetPassword = $resetPassword;
        $this->verifyEmail = $verifyEmail;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/auth/google', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'authenticateWithGoogle'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/auth/email/register', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'registerWithEmail'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/auth/email/login', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'loginWithEmail'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/auth/refresh', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'refresh'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/auth/logout', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'logout'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/auth/password/reset-request', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'requestPasswordReset'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/auth/password/reset', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'resetPassword'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/auth/email/verify', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'verifyEmail'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function authenticateWithGoogle(WP_REST_Request $request)
    {
        try {
            $command = new AuthenticateWithGoogleCommand((string) $request->get_param('id_token'));

            return new WP_REST_Response($this->authenticateWithGoogle->execute($command)->toArray(), 200);
        } catch (InvalidGoogleIdTokenException $exception) {
            return new WP_Error('stageart_invalid_google_id_token', $exception->getMessage(), ['status' => 401]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_authentication_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function registerWithEmail(WP_REST_Request $request)
    {
        try {
            $command = new RegisterWithEmailCommand(
                (string) $request->get_param('email'),
                (string) $request->get_param('password')
            );

            return new WP_REST_Response($this->registerWithEmail->execute($command)->toArray(), 201);
        } catch (EmailAlreadyInUseException $exception) {
            return new WP_Error('stageart_email_already_in_use', $exception->getMessage(), ['status' => 409]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_registration_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function loginWithEmail(WP_REST_Request $request)
    {
        try {
            $command = new AuthenticateWithEmailCommand(
                (string) $request->get_param('email'),
                (string) $request->get_param('password')
            );

            return new WP_REST_Response($this->authenticateWithEmail->execute($command)->toArray(), 200);
        } catch (InvalidCredentialsException $exception) {
            return new WP_Error('stageart_invalid_credentials', $exception->getMessage(), ['status' => 401]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function refresh(WP_REST_Request $request)
    {
        try {
            $command = new RefreshAccessTokenCommand((string) $request->get_param('refresh_token'));

            return new WP_REST_Response($this->refreshAccessToken->execute($command)->toArray(), 200);
        } catch (InvalidRefreshTokenException $exception) {
            return new WP_Error('stageart_invalid_refresh_token', $exception->getMessage(), ['status' => 401]);
        }
    }

    /**
     * @return WP_REST_Response
     */
    public function logout(WP_REST_Request $request)
    {
        $command = new LogoutCommand((string) $request->get_param('refresh_token'));
        $this->logout->execute($command);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * @return WP_REST_Response
     */
    public function requestPasswordReset(WP_REST_Request $request)
    {
        $command = new RequestPasswordResetCommand((string) $request->get_param('email'));
        $this->requestPasswordReset->execute($command);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function resetPassword(WP_REST_Request $request)
    {
        try {
            $command = new ResetPasswordCommand(
                (string) $request->get_param('token'),
                (string) $request->get_param('new_password')
            );
            $this->resetPassword->execute($command);

            return new WP_REST_Response(['success' => true], 200);
        } catch (InvalidPasswordResetTokenException $exception) {
            return new WP_Error('stageart_invalid_password_reset_token', $exception->getMessage(), ['status' => 401]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_password_reset_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function verifyEmail(WP_REST_Request $request)
    {
        try {
            $command = new VerifyEmailCommand((string) $request->get_param('token'));
            $this->verifyEmail->execute($command);

            return new WP_REST_Response(['success' => true], 200);
        } catch (InvalidEmailVerificationTokenException $exception) {
            return new WP_Error('stageart_invalid_email_verification_token', $exception->getMessage(), ['status' => 401]);
        }
    }
}
