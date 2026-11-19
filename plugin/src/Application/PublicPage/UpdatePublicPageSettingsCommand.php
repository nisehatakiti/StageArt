<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\PublicPageSite;

final class UpdatePublicPageSettingsCommand
{
    public PublicPageSite $site;
    public string $ownerId;
    public int $requestedByWordPressUserId;
    /** @var array<string, mixed> mode / preset / custom */
    public array $theme;
    /** @var array<string, mixed> enabled / type / preset / title / description
     * (the own image is set only by uploading it) */
    public array $hero;

    /**
     * @param array<string, mixed> $theme
     * @param array<string, mixed> $hero
     */
    public function __construct(PublicPageSite $site, string $ownerId, int $requestedByWordPressUserId, array $theme, array $hero)
    {
        $this->site = $site;
        $this->ownerId = $ownerId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->theme = $theme;
        $this->hero = $hero;
    }
}
