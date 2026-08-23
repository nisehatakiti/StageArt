<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Account\AccountAccessDeniedException;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Account\ListAccountsForOrganizationQuery;
use StageArt\Application\Account\ListAccountsUseCase;
use StageArt\Application\Organization\OrganizationNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * permission_callback only checks authentication; the actual Organization
 * Scope decision happens inside each Use Case and is mapped to a 403
 * here, same pattern as OrganizationRestController.
 */
final class AccountRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateAccountUseCase $createAccount;
    private ListAccountsUseCase $listAccounts;

    public function __construct(CreateAccountUseCase $createAccount, ListAccountsUseCase $listAccounts)
    {
        $this->createAccount = $createAccount;
        $this->listAccounts = $listAccounts;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/accounts', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
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
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListAccountsForOrganizationQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listAccounts->execute($query)),
                200
            );
        } catch (AccountAccessDeniedException $exception) {
            return new WP_Error('stageart_account_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_account_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateAccountCommand(
                get_current_user_id(),
                (string) $request->get_param('id'),
                (string) $request->get_param('name'),
                (string) $request->get_param('type'),
                $this->stringOrNull($request->get_param('code')),
                $this->stringOrNull($request->get_param('parent_account_id'))
            );

            return new WP_REST_Response($this->createAccount->execute($command)->toArray(), 201);
        } catch (AccountAccessDeniedException $exception) {
            return new WP_Error('stageart_account_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_account_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @param mixed $value
     */
    private function stringOrNull($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
