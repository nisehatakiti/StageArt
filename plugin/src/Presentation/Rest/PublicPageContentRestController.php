<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\PublicPage\DeletePublicPageContentUseCase;
use StageArt\Application\PublicPage\GetPublicPageContentUseCase;
use StageArt\Application\PublicPage\PublicPageAccessDeniedException;
use StageArt\Application\PublicPage\PublicPageContentNotFoundException;
use StageArt\Application\PublicPage\PublicPageOwnerNotFoundException;
use StageArt\Application\PublicPage\SavePublicPageContentUseCase;
use StageArt\Application\PublicPage\UpdatePublicPageContentLayoutUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * コンテンツ tab of the Organization edit screen (StageArtCore's homepage
 * コンテンツブロック + 自由コンテンツ):
 * - GET    /organizations/{id}/public-page/content            layout + contents + choices
 * - PUT    /organizations/{id}/public-page/content-layout     {"blocks": [...]}
 * - POST   /organizations/{id}/public-page/contents           {"title", "body", "status"}
 * - PUT    /organizations/{id}/public-page/contents/{cid}     {"title", "body", "status"}
 * - DELETE /organizations/{id}/public-page/contents/{cid}
 *
 * As PublicPageSettingsRestController: permission_callback only requires a
 * login; who may edit is decided in the use cases (PublicPageAuthorization)
 * and mapped to 403 here.
 */
final class PublicPageContentRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetPublicPageContentUseCase $getContent;
    private UpdatePublicPageContentLayoutUseCase $updateLayout;
    private SavePublicPageContentUseCase $saveContent;
    private DeletePublicPageContentUseCase $deleteContent;

    public function __construct(
        GetPublicPageContentUseCase $getContent,
        UpdatePublicPageContentLayoutUseCase $updateLayout,
        SavePublicPageContentUseCase $saveContent,
        DeletePublicPageContentUseCase $deleteContent
    ) {
        $this->getContent = $getContent;
        $this->updateLayout = $updateLayout;
        $this->saveContent = $saveContent;
        $this->deleteContent = $deleteContent;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/public-page/content', [
            ['methods' => 'GET', 'callback' => [$this, 'get'], 'permission_callback' => [$this, 'require_login']],
        ]);
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/public-page/content-layout', [
            ['methods' => 'PUT', 'callback' => [$this, 'updateLayout'], 'permission_callback' => [$this, 'require_login']],
        ]);
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/public-page/contents', [
            ['methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => [$this, 'require_login']],
        ]);
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/public-page/contents/(?P<content_id>[^/]+)', [
            ['methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => [$this, 'require_login']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete'], 'permission_callback' => [$this, 'require_login']],
        ]);
    }

    public function require_login(): bool
    {
        return is_user_logged_in();
    }

    /** @return WP_REST_Response|WP_Error */
    public function get(WP_REST_Request $request)
    {
        return $this->respond(fn () => $this->getContent->execute((string) $request->get_param('id'), get_current_user_id())->toArray());
    }

    /** @return WP_REST_Response|WP_Error */
    public function updateLayout(WP_REST_Request $request)
    {
        return $this->respond(fn () => $this->updateLayout->execute(
            (string) $request->get_param('id'),
            get_current_user_id(),
            $request->get_param('blocks')
        )->toArray());
    }

    /** @return WP_REST_Response|WP_Error */
    public function create(WP_REST_Request $request)
    {
        return $this->respond(fn () => $this->save($request, null), 201);
    }

    /** @return WP_REST_Response|WP_Error */
    public function update(WP_REST_Request $request)
    {
        return $this->respond(fn () => $this->save($request, (string) $request->get_param('content_id')));
    }

    /** @return WP_REST_Response|WP_Error */
    public function delete(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request) {
            $this->deleteContent->execute((string) $request->get_param('id'), get_current_user_id(), (string) $request->get_param('content_id'));

            return null;
        }, 204);
    }

    private function save(WP_REST_Request $request, ?string $contentId): array
    {
        $title = $request->get_param('title');
        $body = $request->get_param('body') ?? '';
        $status = $request->get_param('status');

        if (! is_string($title) || ! is_string($body) || ! is_string($status)) {
            throw new InvalidArgumentException('"title", "body" and "status" must be strings.');
        }

        return $this->saveContent->execute((string) $request->get_param('id'), get_current_user_id(), $contentId, $title, $body, $status);
    }

    /**
     * @param callable(): ?array $action
     *
     * @return WP_REST_Response|WP_Error
     */
    private function respond(callable $action, int $status = 200)
    {
        try {
            return new WP_REST_Response($action(), $status);
        } catch (PublicPageAccessDeniedException $exception) {
            return new WP_Error('stageart_public_page_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (PublicPageOwnerNotFoundException $exception) {
            return new WP_Error('stageart_public_page_owner_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (PublicPageContentNotFoundException $exception) {
            return new WP_Error('stageart_public_page_content_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_public_page_content_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
