<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Media;

use StageArt\Application\Media\ImageOptimizationPolicy;
use StageArt\Application\Media\ImageRejectedException;
use StageArt\Application\PublicPage\HeroImageUploadRejectedException;
use StageArt\Application\PublicPage\HeroImageUploaderInterface;

/**
 * Stores an own hero image (Organization and Production alike) in the
 * WordPress uploads directory - no attachment post, only the public URL is
 * kept, as before.
 *
 * - Received up to 10MB; the file type is checked from the file's bytes
 *   (JPEG / PNG / WebP / GIF).
 * - Up to 5MB it is stored as uploaded (wp_handle_upload()) once it has
 *   been checked to decode; over 5MB it is re-encoded on the server first
 *   (ImagickImageOptimizer, ImageOptimizationPolicy) and the 5MB-or-less
 *   result is stored (wp_handle_sideload()). If that is not possible the
 *   upload is rejected and nothing is stored.
 * - Before/after format, size and resolution go to the PHP error log (no
 *   file names or contents).
 */
final class WordPressHeroImageUploader implements HeroImageUploaderInterface
{
    private const ALLOWED_MIME_TYPES = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    public const MAX_SIZE_BYTES = ImageOptimizationPolicy::MAX_STORED_BYTES;

    private ImagickImageOptimizer $optimizer;

    public function __construct(?ImagickImageOptimizer $optimizer = null)
    {
        $this->optimizer = $optimizer ?? new ImagickImageOptimizer(new ImageOptimizationPolicy());
    }

    public function upload(array $file): string
    {
        $error = $file['error'] ?? null;

        // PHP's own size limits (upload_max_filesize / form MAX_FILE_SIZE).
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new HeroImageUploadRejectedException(ImageOptimizationPolicy::MESSAGE_TOO_LARGE);
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new HeroImageUploadRejectedException('ファイルのアップロードに失敗しました。');
        }

        if (! isset($file['size']) || $file['size'] <= 0) {
            throw new HeroImageUploadRejectedException('ファイルのアップロードに失敗しました。');
        }

        if ($file['size'] > ImageOptimizationPolicy::MAX_RECEIVED_BYTES) {
            throw new HeroImageUploadRejectedException(ImageOptimizationPolicy::MESSAGE_TOO_LARGE);
        }

        if (! isset($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            throw new HeroImageUploadRejectedException('ファイルのアップロードに失敗しました。');
        }

        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'] ?? '', self::ALLOWED_MIME_TYPES);

        if (empty($checked['ext']) || empty($checked['type'])) {
            throw new HeroImageUploadRejectedException(ImageOptimizationPolicy::MESSAGE_UNSUPPORTED);
        }

        try {
            $outcome = $this->optimizer->prepare($file['tmp_name'], (string) $checked['type']);
        } catch (ImageRejectedException $exception) {
            error_log(sprintf('StageArt hero image rejected: %s %.2fMB - %s', $checked['type'], $file['size'] / 1048576, $exception->getMessage()));

            throw new HeroImageUploadRejectedException($exception->getMessage());
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $optimizedPath = $outcome->optimizedPath;

        try {
            if ($optimizedPath === null) {
                $result = wp_handle_upload($file, ['test_form' => false, 'mimes' => self::ALLOWED_MIME_TYPES]);
            } else {
                // wp_handle_sideload() takes the file array by reference.
                $optimizedFile = [
                    'name' => is_string($checked['proper_filename'] ?? null) && $checked['proper_filename'] !== ''
                        ? $checked['proper_filename']
                        : (string) ($file['name'] ?? 'hero'),
                    'type' => $outcome->mimeType,
                    'tmp_name' => $optimizedPath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => (int) filesize($optimizedPath),
                ];
                $result = wp_handle_sideload($optimizedFile, ['test_form' => false, 'mimes' => self::ALLOWED_MIME_TYPES]);
            }
        } finally {
            // wp_handle_sideload() moved it on success; never leave it behind otherwise.
            if ($optimizedPath !== null && is_file($optimizedPath)) {
                @unlink($optimizedPath);
            }
        }

        if (! isset($result['url'])) {
            throw new HeroImageUploadRejectedException(
                is_string($result['error'] ?? null) ? $result['error'] : 'アップロードに失敗しました。'
            );
        }

        error_log('StageArt hero image stored: ' . $outcome->summary());

        // The uploads URL is built from the siteurl option, which can say
        // http:// while the API is reached over https (dev-api did); give it
        // the scheme of this request, as plugins_url() does for the preset
        // images, so a public page on https never gets an http:// image.
        return set_url_scheme($result['url']);
    }
}
