<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Media;

use StageArt\Application\Organization\OrganizationLogoResizePolicy;
use StageArt\Application\Organization\OrganizationLogoUploadRejectedException;

/**
 * Reduces a newly uploaded Organization logo's stored size by scaling it
 * down with WordPress's own image editor (`wp_get_image_editor()`, i.e.
 * WP_Image_Editor_Imagick or WP_Image_Editor_GD, whichever the server
 * supports), following OrganizationLogoResizePolicy (JPEG/PNG/WebP over
 * the long-edge limit only; never cropped or enlarged; GIF untouched).
 * Only new uploads pass through here; stored logos are never touched.
 *
 * - The output keeps the input's format and MIME type, so the upload's
 *   file name/extension stays valid. Both editors keep PNG/WebP alpha.
 * - JPEG EXIF orientation is applied before scaling (re-encoding drops the
 *   EXIF data that would otherwise have rotated the image on display).
 *   Both editors read it only via exif_read_data(); without the PHP exif
 *   extension a large JPEG is stored unchanged and that is logged.
 * - A large JPEG is first decoded by GD in strict mode to reject a file
 *   cut off part-way (verified with GD only; with no GD JPEG support the
 *   check is skipped and logged - Imagick's own behavior is unverified).
 * - No editor on the server supports the format (`image_no_editor`): the
 *   original is stored unchanged, exactly as before this class existed,
 *   and that is written to the PHP error log.
 * - Any other failure (unreadable/broken image, resize or save error, an
 *   output that does not match what was asked for) rejects the upload -
 *   nothing is stored and no URL is returned.
 *
 * The uploaded original is only ever read. The scaled copy is written to
 * a new temporary file that the caller owns (and must remove if it does
 * not move it into the uploads directory).
 */
final class WordPressOrganizationLogoShrinker
{
    /** Default long-edge limit in pixels; pass another value to the
     * constructor to change it. */
    public const MAX_LONG_EDGE_PX = OrganizationLogoResizePolicy::DEFAULT_MAX_LONG_EDGE_PX;

    private OrganizationLogoResizePolicy $policy;

    public function __construct(int $maxLongEdge = self::MAX_LONG_EDGE_PX)
    {
        $this->policy = new OrganizationLogoResizePolicy($maxLongEdge);
    }

    public function maxLongEdge(): int
    {
        return $this->policy->maxLongEdge();
    }

    /**
     * @param string $sourcePath the uploaded file (read only)
     * @param string $mimeType   the MIME type verified from the file's own bytes
     *
     * @return string|null path of a new temporary file holding the scaled
     *         image, or null when the original should be stored unchanged.
     */
    public function shrink(string $sourcePath, string $mimeType): ?string
    {
        if (! $this->policy->isResizableMimeType($mimeType)) {
            return null;
        }

        $size = @getimagesize($sourcePath);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new OrganizationLogoUploadRejectedException('画像ファイルを読み込めませんでした。別の画像をお試しください。');
        }

        if (! $this->policy->needsResize($mimeType, $size[0], $size[1])) {
            return null;
        }

        if ($mimeType === 'image/jpeg') {
            // Both WordPress editors read EXIF orientation only through
            // exif_read_data(). Without it a re-encoded JPEG would lose the
            // orientation tag and show sideways, so it is stored unchanged
            // (browsers still apply the original's EXIF orientation).
            if (! is_callable('exif_read_data')) {
                $this->logNotResized('the PHP exif extension is not available, so its EXIF orientation could not be kept', $mimeType, $size);

                return null;
            }

            if ($this->isTruncatedJpeg($sourcePath)) {
                throw new OrganizationLogoUploadRejectedException('画像ファイルが壊れているか、途中までしか送信されていません。別の画像をお試しください。');
            }
        }

        $editor = wp_get_image_editor($sourcePath, ['mime_type' => $mimeType]);

        if (is_wp_error($editor)) {
            if ($editor->get_error_code() === 'image_no_editor') {
                $this->logNotResized('no WordPress image editor supports this format', $mimeType, $size);

                return null;
            }

            throw $this->failed();
        }

        if (method_exists($editor, 'maybe_exif_rotate') && is_wp_error($editor->maybe_exif_rotate())) {
            throw $this->failed();
        }

        // After any EXIF rotation, so a 90-degree turn's swapped width and
        // height are what the output is compared against.
        $oriented = $editor->get_size();

        if (is_wp_error($editor->resize($this->policy->maxLongEdge(), $this->policy->maxLongEdge(), false))) {
            throw $this->failed();
        }

        $base = wp_tempnam('stageart-logo');
        @unlink($base);
        $destination = $base . '.' . $this->policy->extensionFor($mimeType);

        $saved = $editor->save($destination, $mimeType);
        $savedPath = is_array($saved) && isset($saved['path']) && is_string($saved['path']) ? $saved['path'] : $destination;
        $savedSize = is_wp_error($saved) || ! is_file($savedPath) ? false : @getimagesize($savedPath);

        if (
            $savedSize === false
            || ! $this->policy->isAcceptableOutput(
                $mimeType,
                (int) $oriented['width'],
                (int) $oriented['height'],
                (string) ($savedSize['mime'] ?? ''),
                $savedSize[0],
                $savedSize[1]
            )
        ) {
            if (is_file($savedPath)) {
                @unlink($savedPath);
            }

            throw $this->failed();
        }

        return $savedPath;
    }

    /**
     * Decodes the JPEG with GD in strict mode (gd.jpeg_ignore_warning=0),
     * where libjpeg's "premature end of data" warning makes decoding fail;
     * WordPress's own editors decode leniently and would otherwise turn a
     * cut-off upload into a partly grey logo. Bytes appended after the end
     * marker do not trigger it. Returns false whenever the check cannot be
     * made (no GD JPEG support, or ini_set() unavailable) - see the class
     * docblock for what is and is not verified.
     */
    private function isTruncatedJpeg(string $path): bool
    {
        if (! function_exists('imagecreatefromjpeg')) {
            error_log('StageArt: GD JPEG support is not available - Organization logo truncation check skipped.');

            return false;
        }

        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('image');
        }

        $previous = ini_set('gd.jpeg_ignore_warning', '0');

        if ($previous === false) {
            error_log('StageArt: gd.jpeg_ignore_warning cannot be changed - Organization logo truncation check skipped.');

            return false;
        }

        try {
            $image = @imagecreatefromjpeg($path);
        } finally {
            ini_set('gd.jpeg_ignore_warning', $previous);
        }

        if ($image === false) {
            return true;
        }

        unset($image);

        return false;
    }

    /** @param array{0: int, 1: int} $size */
    private function logNotResized(string $reason, string $mimeType, array $size): void
    {
        error_log(sprintf(
            'StageArt: Organization logo stored without resizing (%s %dx%d, limit %dpx) - %s.',
            $mimeType,
            $size[0],
            $size[1],
            $this->policy->maxLongEdge(),
            $reason
        ));
    }

    private function failed(): OrganizationLogoUploadRejectedException
    {
        return new OrganizationLogoUploadRejectedException('画像の縮小処理に失敗しました。別の画像をお試しください。');
    }
}
