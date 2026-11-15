<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Media;

use StageArt\Application\Organization\OrganizationLogoUploadRejectedException;

/**
 * Wraps WordPress's own native upload handling (`wp_handle_upload()`) -
 * the same mechanism the Media Library itself uses to move an uploaded
 * file into `wp-content/uploads` - rather than inventing a separate
 * storage scheme for Organization logos. No WP_Post attachment is
 * created (Organization.logoUrl is a plain URL field, matching
 * Production.flyerUrl's own established convention), so this only ever
 * needs the resulting public URL back.
 *
 * A large JPEG/PNG/WebP is first scaled down (aspect ratio kept, never
 * cropped or enlarged) by WordPressOrganizationLogoShrinker; the scaled
 * copy is then stored through `wp_handle_sideload()` - the same upload
 * handling and checks, for a file that is no longer the raw PHP upload.
 */
final class WordPressOrganizationLogoUploader
{
    /** @var array<string, string> extension(s) => allowed mime type, the
     * shape wp_check_filetype_and_ext()/wp_handle_upload()'s own `mimes`
     * override expect. */
    private const ALLOWED_MIME_TYPES = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    public const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    private WordPressOrganizationLogoShrinker $shrinker;

    public function __construct(?WordPressOrganizationLogoShrinker $shrinker = null)
    {
        $this->shrinker = $shrinker ?? new WordPressOrganizationLogoShrinker();
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     *        one entry of WP_REST_Request::get_file_params(), i.e. the
     *        same shape as a raw $_FILES entry.
     */
    public function upload(array $file): string
    {
        if (! isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new OrganizationLogoUploadRejectedException('ファイルのアップロードに失敗しました。');
        }

        if (! isset($file['size']) || $file['size'] <= 0 || $file['size'] > self::MAX_SIZE_BYTES) {
            throw new OrganizationLogoUploadRejectedException('ファイルサイズは5MB以下にしてください。');
        }

        if (! isset($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            throw new OrganizationLogoUploadRejectedException('ファイルのアップロードに失敗しました。');
        }

        // Verifies the file's actual bytes (not just its extension or the
        // client-supplied Content-Type) against the allow-list - this is
        // the server-side rejection of disguised/invalid files, not a
        // guessed substitute for it.
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'] ?? '', self::ALLOWED_MIME_TYPES);

        if (empty($checked['ext']) || empty($checked['type'])) {
            throw new OrganizationLogoUploadRejectedException('画像ファイル（JPEG, PNG, WEBP, GIF）のみアップロードできます。');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $overrides = [
            'test_form' => false,
            'mimes' => self::ALLOWED_MIME_TYPES,
        ];

        $shrunkPath = $this->shrinker->shrink($file['tmp_name'], $checked['type']);

        if ($shrunkPath === null) {
            $result = wp_handle_upload($file, $overrides);
        } else {
            try {
                // Same name (the format is unchanged, so its extension
                // still matches); sideload moves the scaled copy into
                // wp-content/uploads exactly like wp_handle_upload() would.
                $shrunkFile = [
                    'name' => $file['name'] ?? '',
                    'type' => $checked['type'],
                    'tmp_name' => $shrunkPath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => (int) filesize($shrunkPath),
                ];
                $result = wp_handle_sideload($shrunkFile, $overrides);
            } finally {
                if (is_file($shrunkPath)) {
                    @unlink($shrunkPath);
                }
            }
        }

        if (! isset($result['url'])) {
            throw new OrganizationLogoUploadRejectedException(
                is_string($result['error'] ?? null) ? $result['error'] : 'アップロードに失敗しました。'
            );
        }

        // The uploads URL is built from the siteurl option, which can say
        // http:// while the API is reached over https (dev-api did); give it
        // the scheme of this request (is_ssl(), never a client header), as
        // the hero image uploader does, so an https page gets an https logo.
        return set_url_scheme($result['url']);
    }

    /**
     * Removes a file upload() stored that ended up not being saved as the
     * logo. Only a file directly under wp-content/uploads, addressed by an
     * uploads URL, is ever removed.
     */
    public function discard(string $url): void
    {
        $uploads = wp_upload_dir(null, false);

        if (! empty($uploads['error']) || empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return;
        }

        $baseUrl = preg_replace('#^https?:#i', '', (string) $uploads['baseurl']);
        $relativeUrl = preg_replace('#^https?:#i', '', $url);

        if (! is_string($baseUrl) || ! is_string($relativeUrl) || strpos($relativeUrl, $baseUrl . '/') !== 0) {
            return;
        }

        $relative = substr($relativeUrl, strlen($baseUrl) + 1);

        if ($relative === '' || strpos($relative, '..') !== false) {
            return;
        }

        $path = rtrim((string) $uploads['basedir'], '/\\') . '/' . $relative;

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
