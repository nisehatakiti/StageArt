<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\PublicPage\HeroImageUploaderInterface;

final class FakeHeroImageUploader implements HeroImageUploaderInterface
{
    /** @var list<array<string, mixed>> */
    public array $uploaded = [];

    private string $url;

    public function __construct(string $url = 'https://api.example.test/wp-content/uploads/2026/10/hero.jpg')
    {
        $this->url = $url;
    }

    public function upload(array $file): string
    {
        $this->uploaded[] = $file;

        return $this->url;
    }
}
