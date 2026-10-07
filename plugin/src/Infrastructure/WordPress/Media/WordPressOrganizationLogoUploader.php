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

        $result = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => self::ALLOWED_MIME_TYPES,
        ]);

        if (! isset($result['url'])) {
            throw new OrganizationLogoUploadRejectedException(
                is_string($result['error'] ?? null) ? $result['error'] : 'アップロードに失敗しました。'
            );
        }

        return $result['url'];
    }
}
