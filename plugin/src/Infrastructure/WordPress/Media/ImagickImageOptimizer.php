<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Media;

use Imagick;
use ImagickException;
use StageArt\Application\Media\ImageOptimizationOutcome;
use StageArt\Application\Media\ImageOptimizationPolicy;
use StageArt\Application\Media\ImageRejectedException;
use StageArt\Application\Media\JpegStructure;
use Throwable;

/**
 * Checks an uploaded image and, when it is over the stored limit, re-encodes
 * it with Imagick following ImageOptimizationPolicy. Not tied to the hero:
 * any upload path can pass a file and its verified MIME type.
 *
 * - The uploaded file is only read. A re-encoded image is written to a new
 *   temporary file (returned in the outcome; the caller moves or deletes
 *   it). On any failure nothing is left behind and ImageRejectedException
 *   is thrown - never an over-limit original as a fallback.
 * - Every image is decoded once before it is accepted, also one kept as
 *   uploaded, so broken files are refused. Truncated JPEGs decode without
 *   an error here, so their structure is checked (JpegStructure).
 * - The format ImageMagick reads from the bytes must match the MIME type.
 * - JPEG: EXIF orientation applied (autoOrient) before re-encoding; other
 *   metadata (EXIF incl. location) is removed from a re-encoded image, the
 *   colour profile (ICC) is kept. PNG/WebP keep their alpha channel. An
 *   animated image (several frames) is never re-encoded.
 * - Imagick's own memory use is capped (it spills to disk beyond that) and
 *   the pixel count is checked from the header before decoding.
 * - Without the Imagick extension, images up to the stored limit are still
 *   accepted (checked with getimagesize()); larger ones are rejected.
 */
final class ImagickImageOptimizer
{
    private const IMAGICK_MEMORY_LIMIT = 256 * 1024 * 1024;
    private const IMAGICK_MAP_LIMIT = 512 * 1024 * 1024;

    private ImageOptimizationPolicy $policy;
    private string $temporaryDirectory;

    public function __construct(ImageOptimizationPolicy $policy, ?string $temporaryDirectory = null)
    {
        $this->policy = $policy;
        $this->temporaryDirectory = $temporaryDirectory
            ?? (function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir());
    }

    public static function isAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(Imagick::class);
    }

    /**
     * @throws ImageRejectedException
     */
    public function prepare(string $path, string $mimeType): ImageOptimizationOutcome
    {
        $bytes = is_file($path) ? (int) filesize($path) : 0;

        if ($bytes <= 0) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_UNREADABLE);
        }

        if ($this->policy->exceedsReceivedLimit($bytes)) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_TOO_LARGE);
        }

        if (! $this->policy->isSupported($mimeType)) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_UNSUPPORTED);
        }

        if (! self::isAvailable()) {
            return $this->prepareWithoutImagick($path, $mimeType, $bytes);
        }

        self::capResources();

        [$width, $height, $frames] = $this->inspect($path, $mimeType);

        if ($mimeType === 'image/jpeg' && ! JpegStructure::isComplete((string) file_get_contents($path))) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_BROKEN);
        }

        $needsOptimization = $this->policy->exceedsStoredLimit($bytes);

        if ($needsOptimization && $frames > 1) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_ANIMATED_TOO_LARGE);
        }

        $candidates = $this->policy->candidates($mimeType);
        $image = $this->decode($path, $mimeType, $width, $height, $needsOptimization ? $candidates[0]['longEdge'] : 1600);

        try {
            if (! $needsOptimization) {
                return new ImageOptimizationOutcome(null, $mimeType, $bytes, $width, $height, $frames, $bytes, $width, $height);
            }

            return $this->reencode($image, $mimeType, $bytes, $width, $height, $candidates);
        } finally {
            $image->clear();
        }
    }

    private function prepareWithoutImagick(string $path, string $mimeType, int $bytes): ImageOptimizationOutcome
    {
        if ($this->policy->exceedsStoredLimit($bytes)) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_NO_OPTIMIZER);
        }

        $size = @getimagesize($path);

        if ($size === false || $size[0] < 1 || $size[1] < 1 || ($size['mime'] ?? '') !== $mimeType) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_UNREADABLE);
        }

        if ($mimeType === 'image/jpeg' && ! JpegStructure::isComplete((string) file_get_contents($path))) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_BROKEN);
        }

        return new ImageOptimizationOutcome(null, $mimeType, $bytes, $size[0], $size[1], 1, $bytes, $size[0], $size[1]);
    }

    /**
     * Header only (no pixel decoding): format, size, frame count.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function inspect(string $path, string $mimeType): array
    {
        $ping = new Imagick();

        try {
            $ping->pingImage($path);
            $format = strtoupper((string) $ping->getImageFormat());
            $width = (int) $ping->getImageWidth();
            $height = (int) $ping->getImageHeight();
            $frames = (int) $ping->getNumberImages();
        } catch (Throwable $exception) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_UNREADABLE);
        } finally {
            $ping->clear();
        }

        if ($format !== $this->policy->imagickFormatFor($mimeType) || $width < 1 || $height < 1) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_UNREADABLE);
        }

        if ($this->policy->exceedsPixelLimit($width, $height)) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_TOO_MANY_PIXELS);
        }

        return [$width, $height, max(1, $frames)];
    }

    /** Full decode (a broken file fails here); a JPEG is decoded at a reduced scale when that is enough. */
    private function decode(string $path, string $mimeType, int $width, int $height, int $longEdge): Imagick
    {
        $image = new Imagick();

        try {
            if ($mimeType === 'image/jpeg') {
                // libjpeg then decodes at the smallest 1/2, 1/4 or 1/8 scale that
                // is still at least this size (stored orientation, as the header).
                [$hintWidth, $hintHeight] = $this->policy->fitWithin($width, $height, $longEdge);
                $image->setOption('jpeg:size', $hintWidth . 'x' . $hintHeight);
            }

            $image->readImage($path);
        } catch (Throwable $exception) {
            $image->clear();

            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_BROKEN);
        }

        return $image;
    }

    /**
     * @param list<array{longEdge: int, quality: int|null}> $candidates
     */
    private function reencode(Imagick $image, string $mimeType, int $bytes, int $width, int $height, array $candidates): ImageOptimizationOutcome
    {
        try {
            if ($mimeType === 'image/jpeg') {
                $image->autoOrient();
            }

            $profiles = $image->getImageProfiles('icc', true);
            $icc = is_array($profiles) && isset($profiles['icc']) ? $profiles['icc'] : null;
            // The decoded (and oriented) size; a JPEG may already be decoded at
            // a reduced scale, never below the first candidate's size.
            $sourceWidth = (int) $image->getImageWidth();
            $sourceHeight = (int) $image->getImageHeight();

            $tried = [];

            foreach ($candidates as $candidate) {
                // fitWithin() never enlarges.
                [$targetWidth, $targetHeight] = $this->policy->fitWithin($sourceWidth, $sourceHeight, $candidate['longEdge']);

                // A smaller image hits the same size for several long edges: encode each result once.
                $key = $targetWidth . 'x' . $targetHeight . '@' . ($candidate['quality'] ?? '-');
                if (isset($tried[$key])) {
                    continue;
                }
                $tried[$key] = true;

                $copy = clone $image;

                try {
                    $copy->stripImage();

                    if ($icc !== null) {
                        $copy->profileImage('icc', $icc);
                    }

                    if ($targetWidth !== $sourceWidth || $targetHeight !== $sourceHeight) {
                        $copy->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1);
                    }

                    $this->setOutputFormat($copy, $mimeType, $candidate['quality']);
                    $blob = $copy->getImageBlob();
                } finally {
                    $copy->clear();
                }

                if ($this->policy->exceedsStoredLimit(strlen($blob))) {
                    continue;
                }

                $outputPath = $this->writeTemporary($blob, $mimeType);
                [$outWidth, $outHeight] = $this->verifyOutput($outputPath, $mimeType);

                return new ImageOptimizationOutcome(
                    $outputPath,
                    $mimeType,
                    $bytes,
                    $width,
                    $height,
                    1,
                    strlen($blob),
                    $outWidth,
                    $outHeight,
                    $candidate['quality']
                );
            }
        } catch (ImageRejectedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_FAILED);
        }

        throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_CANNOT_FIT);
    }

    private function setOutputFormat(Imagick $image, string $mimeType, ?int $quality): void
    {
        switch ($mimeType) {
            case 'image/jpeg':
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality((int) $quality);
                $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
                break;
            case 'image/webp':
                $image->setImageFormat('webp');
                $image->setOption('webp:lossless', 'false');
                $image->setOption('webp:method', '4');
                $image->setImageCompressionQuality((int) $quality);
                break;
            case 'image/png':
                $image->setImageFormat('png');
                $image->setOption('png:compression-level', '9');
                $image->setImageDepth(8);
                break;
            case 'image/gif':
                $image->setImageFormat('gif');
                break;
        }
    }

    private function writeTemporary(string $blob, string $mimeType): string
    {
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mimeType];
        $base = tempnam($this->temporaryDirectory, 'stageart-img');

        if ($base === false) {
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_FAILED);
        }

        $path = $base . '.' . $extension;
        @unlink($base);

        if (file_exists($path) || file_put_contents($path, $blob, LOCK_EX) !== strlen($blob)) {
            @unlink($path);
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_FAILED);
        }

        return $path;
    }

    /** @return array{0: int, 1: int} */
    private function verifyOutput(string $path, string $mimeType): array
    {
        $check = new Imagick();

        try {
            $check->pingImage($path);
            $format = strtoupper((string) $check->getImageFormat());
            $size = [(int) $check->getImageWidth(), (int) $check->getImageHeight()];
        } catch (ImagickException $exception) {
            $format = '';
            $size = [0, 0];
        } finally {
            $check->clear();
        }

        if ($format !== $this->policy->imagickFormatFor($mimeType) || $size[0] < 1 || $size[1] < 1) {
            @unlink($path);
            throw new ImageRejectedException(ImageOptimizationPolicy::MESSAGE_FAILED);
        }

        return $size;
    }

    /** Lowers (never raises) Imagick's per-process memory use; beyond it ImageMagick uses disk. */
    private static function capResources(): void
    {
        foreach ([Imagick::RESOURCETYPE_MEMORY => self::IMAGICK_MEMORY_LIMIT, Imagick::RESOURCETYPE_MAP => self::IMAGICK_MAP_LIMIT] as $type => $limit) {
            $current = (int) Imagick::getResourceLimit($type);

            if ($current <= 0 || $current > $limit) {
                Imagick::setResourceLimit($type, $limit);
            }
        }
    }
}
