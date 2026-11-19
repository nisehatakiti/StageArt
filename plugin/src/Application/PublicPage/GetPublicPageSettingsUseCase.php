<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\PublicPageSettings;
use StageArt\Domain\PublicPage\PublicPageSettingsRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/** Loads the 公開ページ設定 tab (saved settings, or the defaults for a page never configured). */
final class GetPublicPageSettingsUseCase
{
    private PublicPageSettingsRepositoryInterface $settings;
    private HeroPresetCatalogInterface $heroPresets;
    private PublicPageAuthorization $authorization;

    public function __construct(
        PublicPageSettingsRepositoryInterface $settings,
        HeroPresetCatalogInterface $heroPresets,
        PublicPageAuthorization $authorization
    ) {
        $this->settings = $settings;
        $this->heroPresets = $heroPresets;
        $this->authorization = $authorization;
    }

    public function execute(PublicPageSite $site, string $ownerId, int $requestedByWordPressUserId): PublicPageSettingsResult
    {
        $this->authorization->assertCanEdit($site, $ownerId, $requestedByWordPressUserId);

        $settings = $this->settings->find($site, $ownerId) ?? PublicPageSettings::defaults($site, $ownerId);

        return new PublicPageSettingsResult($settings, $this->heroPresets->presets($site));
    }
}
