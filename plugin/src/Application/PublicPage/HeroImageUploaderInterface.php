<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

interface HeroImageUploaderInterface
{
    /**
     * Stores an uploaded image and returns its public URL.
     *
     * @param array<string, mixed> $file one $_FILES-shaped entry
     *
     * @throws HeroImageUploadRejectedException
     */
    public function upload(array $file): string;
}
