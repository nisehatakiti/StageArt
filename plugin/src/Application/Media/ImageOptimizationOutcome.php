<?php

declare(strict_types=1);

namespace StageArt\Application\Media;

/**
 * What happened to one uploaded image: kept as uploaded (path null) or
 * re-encoded into a new temporary file the caller owns. Sizes only - no
 * file names or contents - so it can be logged.
 */
final class ImageOptimizationOutcome
{
    public ?string $optimizedPath;
    public string $mimeType;
    public int $bytesBefore;
    public int $widthBefore;
    public int $heightBefore;
    public int $frames;
    public int $bytesAfter;
    public int $widthAfter;
    public int $heightAfter;
    public ?int $quality;

    public function __construct(
        ?string $optimizedPath,
        string $mimeType,
        int $bytesBefore,
        int $widthBefore,
        int $heightBefore,
        int $frames,
        int $bytesAfter,
        int $widthAfter,
        int $heightAfter,
        ?int $quality = null
    ) {
        $this->optimizedPath = $optimizedPath;
        $this->mimeType = $mimeType;
        $this->bytesBefore = $bytesBefore;
        $this->widthBefore = $widthBefore;
        $this->heightBefore = $heightBefore;
        $this->frames = $frames;
        $this->bytesAfter = $bytesAfter;
        $this->widthAfter = $widthAfter;
        $this->heightAfter = $heightAfter;
        $this->quality = $quality;
    }

    public function wasOptimized(): bool
    {
        return $this->optimizedPath !== null;
    }

    public function summary(): string
    {
        return sprintf(
            '%s %s %dx%d%s -> %s %dx%d%s',
            $this->mimeType,
            self::megabytes($this->bytesBefore),
            $this->widthBefore,
            $this->heightBefore,
            $this->frames > 1 ? " ({$this->frames} frames)" : '',
            $this->wasOptimized() ? self::megabytes($this->bytesAfter) : 'kept as uploaded',
            $this->widthAfter,
            $this->heightAfter,
            $this->quality !== null ? " q{$this->quality}" : ''
        );
    }

    private static function megabytes(int $bytes): string
    {
        return sprintf('%.2fMB', $bytes / 1048576);
    }
}
