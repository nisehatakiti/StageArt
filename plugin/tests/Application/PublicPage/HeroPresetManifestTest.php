<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\PublicPage;

use PHPUnit\Framework\TestCase;

/**
 * The hero manifests copied from StageArtCore must list only files that are
 * actually shipped (full image + settings-tab thumbnail), with unique ids.
 */
final class HeroPresetManifestTest extends TestCase
{
    /** @return array<string, array{0: string, 1: int}> */
    public static function sites(): array
    {
        return ['organization' => ['organization', 25], 'production' => ['production', 35]];
    }

    /** @dataProvider sites */
    public function test_manifest_entries_point_at_shipped_files(string $site, int $expectedCount): void
    {
        $dir = dirname(__DIR__, 3) . '/assets/hero/' . $site;
        $manifest = json_decode((string) file_get_contents($dir . '/manifest.json'), true);

        $this->assertIsArray($manifest);
        $this->assertSame('cover', $manifest['fit']);
        $this->assertSame('center center', $manifest['position']);
        $this->assertCount($expectedCount, $manifest['presets']);

        $ids = array_column($manifest['presets'], 'id');
        $this->assertSame($ids, array_values(array_unique($ids)));

        foreach ($manifest['presets'] as $preset) {
            $this->assertFileExists($dir . '/' . $preset['file']);
            $this->assertFileExists($dir . '/thumbs/' . $preset['file']);
        }
    }
}
