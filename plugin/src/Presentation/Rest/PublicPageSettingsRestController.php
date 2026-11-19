<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Media\ImageOptimizationPolicy;
use StageArt\Application\PublicPage\GetPublicPageSettingsUseCase;
use StageArt\Application\PublicPage\HeroImageUploadRejectedException;
use StageArt\Application\PublicPage\PublicPageAccessDeniedException;
use StageArt\Application\PublicPage\PublicPageOwnerNotFoundException;
use StageArt\Application\PublicPage\UpdatePublicPageHeroImageUseCase;
use StageArt\Application\PublicPage\UpdatePublicPageSettingsCommand;
use StageArt\Application\PublicPage\UpdatePublicPageSettingsUseCase;
use StageArt\Domain\PublicPage\PublicPageSite;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * 公開ページ設定 (theme + hero) of the Organization and Production public
 * pages:
 * - GET/PUT  /organizations/{id}/public-page, /productions/{id}/public-page
 * - POST     .../public-page/hero-image (multipart "file") - own hero image
 *
 * Same split as the other controllers: permission_callback only requires a
 * login, and who may edit which page is decided inside the use cases
 * (PublicPageAuthorization), mapped to 403 here.
 */
final class PublicPageSettingsRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetPublicPageSettingsUseCase $getSettings;
    private UpdatePublicPageSettingsUseCase $updateSettings;
    private UpdatePublicPageHeroImageUseCase $updateHeroImage;

    public function __construct(
        GetPublicPageSettingsUseCase $getSettings,
        UpdatePublicPageSettingsUseCase $updateSettings,
        UpdatePublicPageHeroImageUseCase $updateHeroImage
    ) {
        $this->getSettings = $getSettings;
        $this->updateSettings = $updateSettings;
        $this->updateHeroImage = $updateHeroImage;
    }

    public function register_routes(): void
    {
        foreach (['organizations' => PublicPageSite::ORGANIZATION, 'productions' => PublicPageSite::PRODUCTION] as $collection => $site) {
            register_rest_route(self::API_NAMESPACE, "/{$collection}/(?P<id>[^/]+)/public-page", [
                [
                    'methods' => 'GET',
                    'callback' => fn (WP_REST_Request $request) => $this->get($request, $site),
                    'permission_callback' => [$this, 'require_login'],
                ],
                [
                    'methods' => 'PUT',
                    'callback' => fn (WP_REST_Request $request) => $this->update($request, $site),
                    'permission_callback' => [$this, 'require_login'],
                ],
            ]);

            register_rest_route(self::API_NAMESPACE, "/{$collection}/(?P<id>[^/]+)/public-page/hero-image", [
                [
                    'methods' => 'POST',
                    'callback' => fn (WP_REST_Request $request) => $this->uploadHeroImage($request, $site),
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
    public function get(WP_REST_Request $request, string $site)
    {
        return $this->respond(fn () => $this->getSettings->execute(
            PublicPageSite::fromString($site),
            (string) $request->get_param('id'),
            get_current_user_id()
        ));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request, string $site)
    {
        $theme = $request->get_param('theme');
        $hero = $request->get_param('hero');

        if (! is_array($theme) || ! is_array($hero)) {
            return new WP_Error('stageart_public_page_invalid', '"theme" and "hero" are required.', ['status' => 422]);
        }

        return $this->respond(fn () => $this->updateSettings->execute(new UpdatePublicPageSettingsCommand(
            PublicPageSite::fromString($site),
            (string) $request->get_param('id'),
            get_current_user_id(),
            $theme,
            $hero
        )));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function uploadHeroImage(WP_REST_Request $request, string $site)
    {
        $files = $request->get_file_params();

        if (empty($files['file']) || ! is_array($files['file'])) {
            // A body over PHP's post_max_size reaches here with no files at
            // all: say why (the 10MB limit) instead of "no file".
            $contentLength = (int) $request->get_header('content_length');

            if ($contentLength > ImageOptimizationPolicy::MAX_RECEIVED_BYTES) {
                return new WP_Error('stageart_public_page_hero_image_rejected', ImageOptimizationPolicy::MESSAGE_TOO_LARGE, ['status' => 422]);
            }

            return new WP_Error('stageart_public_page_hero_image_invalid', 'A "file" upload is required.', ['status' => 422]);
        }

        return $this->respond(fn () => $this->updateHeroImage->execute(
            PublicPageSite::fromString($site),
            (string) $request->get_param('id'),
            get_current_user_id(),
            $files['file']
        ));
    }

    /**
     * @param callable(): \StageArt\Application\PublicPage\PublicPageSettingsResult $action
     *
     * @return WP_REST_Response|WP_Error
     */
    private function respond(callable $action)
    {
        try {
            return new WP_REST_Response($action()->toArray(), 200);
        } catch (PublicPageAccessDeniedException $exception) {
            return new WP_Error('stageart_public_page_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (PublicPageOwnerNotFoundException $exception) {
            return new WP_Error('stageart_public_page_owner_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (HeroImageUploadRejectedException $exception) {
            return new WP_Error('stageart_public_page_hero_image_rejected', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_public_page_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
