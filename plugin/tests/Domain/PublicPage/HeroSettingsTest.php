<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\PublicPage;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\PublicPage\HeroSettings;
use StageArt\Domain\PublicPage\PublicPageSite;

final class HeroSettingsTest extends TestCase
{
    private function exists(): callable
    {
        return static fn (string $id): bool => in_array($id, ['sky-blue', 'circle-shadows'], true);
    }

    public function test_enabled_preset_hero(): void
    {
        $hero = HeroSettings::create(PublicPageSite::production(), true, 'preset', 'sky-blue', null, ' 春公演 ', '説明', $this->exists());

        $this->assertSame(
            ['enabled' => true, 'type' => 'preset', 'preset' => 'sky-blue', 'custom_image_url' => null, 'title' => '春公演', 'description' => '説明'],
            $hero->toArray()
        );
    }

    public function test_unknown_preset_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HeroSettings::create(PublicPageSite::production(), true, 'preset', 'not-in-manifest', null, '', '', $this->exists());
    }

    public function test_enabled_preset_hero_needs_a_preset(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HeroSettings::create(PublicPageSite::organization(), true, 'preset', null, null, '', '', $this->exists());
    }

    public function test_enabled_custom_hero_needs_an_uploaded_image(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HeroSettings::create(PublicPageSite::organization(), true, 'custom', null, null, '', '', $this->exists());
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HeroSettings::create(PublicPageSite::organization(), true, 'video', 'circle-shadows', null, '', '', $this->exists());
    }

    public function test_too_long_title_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HeroSettings::create(PublicPageSite::organization(), false, 'preset', null, null, str_repeat('あ', 256), '', $this->exists());
    }

    public function test_production_hero_turned_off_is_cleared_like_stageart_core(): void
    {
        $hero = HeroSettings::create(PublicPageSite::production(), false, 'custom', 'sky-blue', 'https://x.test/a.jpg', 'T', 'D', $this->exists());

        $this->assertSame(HeroSettings::disabled()->toArray(), $hero->toArray());
    }

    public function test_production_preset_forgets_the_own_image_like_stageart_core(): void
    {
        $hero = HeroSettings::create(PublicPageSite::production(), true, 'preset', 'sky-blue', 'https://x.test/a.jpg', '', '', $this->exists());

        $this->assertNull($hero->customImageUrl());
    }

    public function test_organization_hero_keeps_its_values_while_off_like_stageart_core(): void
    {
        $hero = HeroSettings::create(PublicPageSite::organization(), false, 'custom', 'circle-shadows', 'https://x.test/a.jpg', 'T', 'D', $this->exists());

        $this->assertFalse($hero->isEnabled());
        $this->assertSame('circle-shadows', $hero->preset());
        $this->assertSame('https://x.test/a.jpg', $hero->customImageUrl());
        $this->assertSame('T', $hero->title());
    }

    public function test_title_newlines_become_spaces(): void
    {
        $hero = HeroSettings::create(PublicPageSite::organization(), false, 'preset', null, null, "一行目\n二行目", '', $this->exists());

        $this->assertSame('一行目 二行目', $hero->title());
    }

    public function test_stored_values_round_trip(): void
    {
        $hero = HeroSettings::create(PublicPageSite::organization(), true, 'custom', 'circle-shadows', 'https://x.test/a.jpg', 'T', 'D', $this->exists());

        $this->assertSame($hero->toArray(), HeroSettings::fromStored($hero->toArray())->toArray());
    }
}
