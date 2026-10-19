<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

/**
 * The rules for shrinking a newly uploaded Organization logo, kept free of
 * WordPress so they can be unit tested. The actual pixel work happens in
 * Infrastructure (WordPressOrganizationLogoShrinker, via WordPress's own
 * image editor).
 *
 * - Only JPEG/PNG/WebP are ever re-encoded. GIF is not: WordPress's image
 *   editors keep only the first frame of an animated GIF.
 * - Only images whose long edge exceeds the limit are scaled; anything at
 *   or under it is stored exactly as uploaded (no needless re-encode).
 * - Scaling keeps the aspect ratio and never crops or enlarges.
 */
final class OrganizationLogoResizePolicy
{
    /** Default long-edge limit in pixels. */
    public const DEFAULT_MAX_LONG_EDGE_PX = 1024;

    /** @var array<string, string> re-encodable MIME type => extension of the scaled copy */
    private const RESIZABLE_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private int $maxLongEdge;

    public function __construct(int $maxLongEdge = self::DEFAULT_MAX_LONG_EDGE_PX)
    {
        if ($maxLongEdge < 1) {
            throw new \InvalidArgumentException('The logo long-edge limit must be a positive number of pixels.');
        }

        $this->maxLongEdge = $maxLongEdge;
    }

    public function maxLongEdge(): int
    {
        return $this->maxLongEdge;
    }

    public function isResizableMimeType(string $mimeType): bool
    {
        return isset(self::RESIZABLE_MIME_TYPES[$mimeType]);
    }

    /** File extension used for the scaled copy of a resizable MIME type. */
    public function extensionFor(string $mimeType): string
    {
        if (! $this->isResizableMimeType($mimeType)) {
            throw new \InvalidArgumentException(sprintf('%s is not a resizable logo format.', $mimeType));
        }

        return self::RESIZABLE_MIME_TYPES[$mimeType];
    }

    public function needsResize(string $mimeType, int $width, int $height): bool
    {
        return $this->isResizableMimeType($mimeType) && max($width, $height) > $this->maxLongEdge;
    }

    /**
     * Checks an editor's output against what was asked for: same format,
     * long edge within the limit, never larger than the source, and the
     * source aspect ratio kept (allowing the editor's own rounding to the
     * nearest pixel) - i.e. not cropped and not stretched.
     */
    public function isAcceptableOutput(
        string $sourceMimeType,
        int $sourceWidth,
        int $sourceHeight,
        string $outputMimeType,
        int $outputWidth,
        int $outputHeight
    ): bool {
        if ($outputMimeType !== $sourceMimeType || $outputWidth < 1 || $outputHeight < 1) {
            return false;
        }

        if (max($outputWidth, $outputHeight) > $this->maxLongEdge) {
            return false;
        }

        if ($outputWidth > $sourceWidth || $outputHeight > $sourceHeight) {
            return false;
        }

        // Cross-multiplied aspect comparison: a one-pixel rounding of the
        // short edge changes outputW*sourceH (or outputH*sourceW) by at
        // most max(sourceW, sourceH).
        return abs($outputWidth * $sourceHeight - $outputHeight * $sourceWidth) <= max($sourceWidth, $sourceHeight);
    }
}
