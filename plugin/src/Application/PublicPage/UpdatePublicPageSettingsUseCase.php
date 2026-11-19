<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use InvalidArgumentException;
use StageArt\Domain\PublicPage\HeroSettings;
use StageArt\Domain\PublicPage\PublicPageSettings;
use StageArt\Domain\PublicPage\PublicPageSettingsRepositoryInterface;
use StageArt\Domain\PublicPage\ThemeSettings;

/**
 * Saves the theme and hero of one public page. Only this page's own row is
 * written - the Organization / Production content is never touched, and an
 * Organization's settings never change its Productions' settings (or the
 * other way round).
 *
 * Every value is validated server-side; an unknown theme preset, color,
 * font or hero preset is rejected (InvalidArgumentException -> 422) and
 * nothing is saved.
 */
final class UpdatePublicPageSettingsUseCase
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

    public function execute(UpdatePublicPageSettingsCommand $command): PublicPageSettingsResult
    {
        $this->authorization->assertCanEdit($command->site, $command->ownerId, $command->requestedByWordPressUserId);

        $current = $this->settings->find($command->site, $command->ownerId)
            ?? PublicPageSettings::defaults($command->site, $command->ownerId);
        $presets = $this->heroPresets->presets($command->site);
        $presetIds = array_column($presets, 'id');

        $theme = ThemeSettings::fromArray($command->theme, $command->site);
        $hero = HeroSettings::create(
            $command->site,
            (bool) ($command->hero['enabled'] ?? false),
            $this->stringField($command->hero, 'type', HeroSettings::TYPE_PRESET),
            $this->nullableStringField($command->hero, 'preset'),
            $current->hero()->customImageUrl(),
            $this->stringField($command->hero, 'title', ''),
            $this->stringField($command->hero, 'description', ''),
            static fn (string $id): bool => in_array($id, $presetIds, true)
        );

        $updated = $current->withTheme($theme)->withHero($hero);
        $this->settings->save($updated);

        return new PublicPageSettingsResult($updated, $presets);
    }

    /** @param array<string, mixed> $input */
    private function stringField(array $input, string $key, string $default): string
    {
        $value = $input[$key] ?? $default;

        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("hero.{$key} must be a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function nullableStringField(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException("hero.{$key} must be a string.");
        }

        return $value;
    }
}
