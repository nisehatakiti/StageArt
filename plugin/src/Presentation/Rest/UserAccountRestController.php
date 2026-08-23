<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Authentication\ExternalIdentityAlreadyLinkedException;
use StageArt\Application\Authentication\InvalidGoogleIdTokenException;
use StageArt\Application\Authentication\LinkGoogleIdentityCommand;
use StageArt\Application\Authentication\LinkGoogleIdentityUseCase;
use StageArt\Application\UserAccount\ChangePasswordCommand;
use StageArt\Application\UserAccount\ChangePasswordUseCase;
use StageArt\Application\UserAccount\CreateUserAccountCommand;
use StageArt\Application\UserAccount\CreateUserAccountUseCase;
use StageArt\Application\UserAccount\CurrentPasswordIncorrectException;
use StageArt\Application\UserAccount\EmailAlreadyInUseException;
use StageArt\Application\UserAccount\EmailCredentialAlreadyExistsException;
use StageArt\Application\UserAccount\EmailCredentialNotFoundException;
use StageArt\Application\UserAccount\RegisterEmailCredentialCommand;
use StageArt\Application\UserAccount\RegisterEmailCredentialUseCase;
use StageArt\Application\UserAccount\RequestEmailVerificationCommand;
use StageArt\Application\UserAccount\RequestEmailVerificationUseCase;
use StageArt\Application\UserAccount\UserAccountAlreadyExistsException;
use StageArt\Application\UserAccount\UserAccountNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * All routes always act on the requesting WordPress user's own Person/
 * UserAccount (resolved from get_current_user_id(), never from a
 * request parameter), so there is no separate Authorization check for
 * "acting on someone else's UserAccount" to get wrong - the Use Cases
 * structurally cannot reach another Person's data. permission_callback
 * only checks authentication, matching OrganizationRestController's
 * pattern.
 *
 * external-identity (Phase 2 Google Authentication) is the opt-in
 * legacy-user migration path (this Phase's design report §9): the
 * caller must already be authenticated - via whatever method got them
 * logged in, legacy WordPress Application Password included - before
 * they can attach a Google identity to their own UserAccount. This is
 * deliberately kept in this Controller rather than
 * AuthenticationRestController: unlike /auth/google (which establishes
 * a session from nothing), this route's whole safety property comes
 * from the caller already being authenticated, matching this
 * Controller's existing routes exactly, not AuthenticationRestController's
 * public ones.
 */
final class UserAccountRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateUserAccountUseCase $createUserAccount;
    private RegisterEmailCredentialUseCase $registerEmailCredential;
    private LinkGoogleIdentityUseCase $linkGoogleIdentity;
    private ChangePasswordUseCase $changePassword;
    private RequestEmailVerificationUseCase $requestEmailVerification;

    public function __construct(
        CreateUserAccountUseCase $createUserAccount,
        RegisterEmailCredentialUseCase $registerEmailCredential,
        LinkGoogleIdentityUseCase $linkGoogleIdentity,
        ChangePasswordUseCase $changePassword,
        RequestEmailVerificationUseCase $requestEmailVerification
    ) {
        $this->createUserAccount = $createUserAccount;
        $this->registerEmailCredential = $registerEmailCredential;
        $this->linkGoogleIdentity = $linkGoogleIdentity;
        $this->changePassword = $changePassword;
        $this->requestEmailVerification = $requestEmailVerification;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/user-accounts', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/user-accounts/email-credential', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'registerEmailCredential'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/user-accounts/external-identity', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'linkExternalIdentity'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/user-accounts/email-credential/password', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'changePassword'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/user-accounts/email-credential/verify-request', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'requestEmailVerification'],
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
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateUserAccountCommand(get_current_user_id());

            return new WP_REST_Response($this->createUserAccount->execute($command)->toArray(), 201);
        } catch (UserAccountAlreadyExistsException $exception) {
            return new WP_Error('stageart_user_account_already_exists', $exception->getMessage(), ['status' => 409]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function registerEmailCredential(WP_REST_Request $request)
    {
        try {
            $command = new RegisterEmailCredentialCommand(
                get_current_user_id(),
                (string) $request->get_param('email'),
                (string) $request->get_param('password')
            );

            return new WP_REST_Response($this->registerEmailCredential->execute($command)->toArray(), 201);
        } catch (UserAccountNotFoundException $exception) {
            return new WP_Error('stageart_user_account_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (EmailCredentialAlreadyExistsException $exception) {
            return new WP_Error('stageart_email_credential_already_exists', $exception->getMessage(), ['status' => 409]);
        } catch (EmailAlreadyInUseException $exception) {
            return new WP_Error('stageart_email_already_in_use', $exception->getMessage(), ['status' => 409]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_email_credential_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function linkExternalIdentity(WP_REST_Request $request)
    {
        try {
            $command = new LinkGoogleIdentityCommand(get_current_user_id(), (string) $request->get_param('id_token'));

            return new WP_REST_Response($this->linkGoogleIdentity->execute($command)->toArray(), 200);
        } catch (InvalidGoogleIdTokenException $exception) {
            return new WP_Error('stageart_invalid_google_id_token', $exception->getMessage(), ['status' => 401]);
        } catch (ExternalIdentityAlreadyLinkedException $exception) {
            return new WP_Error('stageart_external_identity_already_linked', $exception->getMessage(), ['status' => 409]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function changePassword(WP_REST_Request $request)
    {
        try {
            $command = new ChangePasswordCommand(
                get_current_user_id(),
                (string) $request->get_param('current_password'),
                (string) $request->get_param('new_password')
            );
            $this->changePassword->execute($command);

            return new WP_REST_Response(['success' => true], 200);
        } catch (UserAccountNotFoundException $exception) {
            return new WP_Error('stageart_user_account_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (EmailCredentialNotFoundException $exception) {
            return new WP_Error('stageart_email_credential_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (CurrentPasswordIncorrectException $exception) {
            return new WP_Error('stageart_current_password_incorrect', $exception->getMessage(), ['status' => 401]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_password_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function requestEmailVerification(WP_REST_Request $request)
    {
        try {
            $command = new RequestEmailVerificationCommand(get_current_user_id());
            $this->requestEmailVerification->execute($command);

            return new WP_REST_Response(['success' => true], 200);
        } catch (UserAccountNotFoundException $exception) {
            return new WP_Error('stageart_user_account_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (EmailCredentialNotFoundException $exception) {
            return new WP_Error('stageart_email_credential_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
