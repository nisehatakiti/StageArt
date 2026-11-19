<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Media;

use StageArt\Application\PublicPage\HeroPresetCatalogInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * Reads the hero presets from StageArtCore's manifests, copied unchanged
 * into `assets/hero/{organization,production}/manifest.json` together with
 * the images they list. Entries are read the way StageArtCore's
 * HeroAdmin::readManifest() does (id via sanitize_key, file via basename,
 * entries without id or file skipped, the first entry of an id wins).
 *
 * `thumbs/` holds 480px-wide copies of the same files, made only for the
 * preset grid of the settings tab (the full images are ~2MB each).
 */
final class WordPressHeroPresetCatalog implements HeroPresetCatalogInterface
{
    private string $baseDir;
    private string $baseUrl;
    /** @var array<string, list<array{id: string, label: string, category: string, image_url: string, thumbnail_url: string}>> */
    private array $cache = [];

    public function __construct(string $pluginDir, string $pluginUrl)
    {
        $this->baseDir = trailingslashit($pluginDir) . 'assets/hero/';
        $this->baseUrl = trailingslashit($pluginUrl) . 'assets/hero/';
    }

    public function presets(PublicPageSite $site): array
    {
        $key = $site->toString();

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $path = $this->baseDir . $key . '/manifest.json';
        $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        $out = [];
        $seen = [];

        foreach (is_array($data) ? (array) ($data['presets'] ?? []) : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = sanitize_key((string) ($item['id'] ?? ''));
            $file = basename((string) ($item['file'] ?? ''));

            if ($id === '' || $file === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $thumbnail = is_file($this->baseDir . $key . '/thumbs/' . $file)
                ? $this->baseUrl . $key . '/thumbs/' . rawurlencode($file)
                : $this->baseUrl . $key . '/' . rawurlencode($file);

            $out[] = [
                'id' => $id,
                'label' => sanitize_text_field((string) ($item['label'] ?? $id)),
                'category' => sanitize_text_field((string) ($item['category'] ?? '')),
                'image_url' => $this->baseUrl . $key . '/' . rawurlencode($file),
                'thumbnail_url' => $thumbnail,
            ];
        }

        return $this->cache[$key] = $out;
    }
}
