<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\PublicPageSettings;
use StageArt\Domain\PublicPage\PublicPageSettingsRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * Uploads the page's own hero image and stores its URL in this page's
 * settings (the other theme/hero values are left as they are; whether the
 * own image is actually shown is still decided by hero.enabled / hero.type,
 * saved with the 公開ページ設定 form).
 *
 * The permission check runs before the file is stored, so a requester who
 * may not edit this page never leaves a file behind.
 */
final class UpdatePublicPageHeroImageUseCase
{
    private PublicPageSettingsRepositoryInterface $settings;
    private HeroPresetCatalogInterface $heroPresets;
    private PublicPageAuthorization $authorization;
    private HeroImageUploaderInterface $uploader;

    public function __construct(
        PublicPageSettingsRepositoryInterface $settings,
        HeroPresetCatalogInterface $heroPresets,
        PublicPageAuthorization $authorization,
        HeroImageUploaderInterface $uploader
    ) {
        $this->settings = $settings;
        $this->heroPresets = $heroPresets;
        $this->authorization = $authorization;
        $this->uploader = $uploader;
    }

    /** @param array<string, mixed> $file */
    public function execute(PublicPageSite $site, string $ownerId, int $requestedByWordPressUserId, array $file): PublicPageSettingsResult
    {
        $this->authorization->assertCanEdit($site, $ownerId, $requestedByWordPressUserId);

        $url = $this->uploader->upload($file);

        $current = $this->settings->find($site, $ownerId) ?? PublicPageSettings::defaults($site, $ownerId);
        $updated = $current->withHero($current->hero()->withCustomImageUrl($url));
        $this->settings->save($updated);

        return new PublicPageSettingsResult($updated, $this->heroPresets->presets($site));
    }
}
