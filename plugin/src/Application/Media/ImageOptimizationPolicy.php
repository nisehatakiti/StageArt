<?php

declare(strict_types=1);

namespace StageArt\Application\Media;

/**
 * Size rules for an uploaded image that is optimized on the server before
 * it is stored (first used by the 公開ページ設定 hero image; written so the
 * Organization logo can use it later).
 *
 * - Received: at most 10MB (MAX_RECEIVED_BYTES); larger is rejected.
 * - 5MB or less: stored as uploaded, not re-encoded (after it has been
 *   checked to decode).
 * - Over 5MB: re-encoded in its own format, trying the candidates below in
 *   order (long edge, then quality) until the result is 5MB or less
 *   (MAX_STORED_BYTES). If none fits, the upload is rejected - an image
 *   over 5MB is never stored.
 * - Never enlarged; aspect ratio kept. JPEG/WebP lower their quality step
 *   by step (85 first); PNG/GIF are lossless and only scaled down, PNG
 *   keeps its alpha channel and is never turned into a JPEG.
 * - An animated image (more than one frame) is not re-encoded: over 5MB it
 *   is rejected with its own message, so its animation is never lost.
 * - Over MAX_PIXELS pixels is rejected before decoding (decode cost).
 */
final class ImageOptimizationPolicy
{
    public const MAX_RECEIVED_BYTES = 10 * 1024 * 1024;
    public const MAX_STORED_BYTES = 5 * 1024 * 1024;
    /** 60 megapixels: above the 48-50MP sensors of current phones. */
    public const MAX_PIXELS = 60000000;

    public const MESSAGE_TOO_LARGE = '画像のファイルサイズは10MB以下にしてください。';
    public const MESSAGE_UNSUPPORTED = '画像ファイル（JPEG, PNG, WEBP, GIF）のみアップロードできます。';
    public const MESSAGE_UNREADABLE = '画像ファイルを読み込めませんでした。別の画像をお試しください。';
    public const MESSAGE_BROKEN = '画像ファイルが壊れているか、途中までしか送信されていません。別の画像をお試しください。';
    public const MESSAGE_TOO_MANY_PIXELS = '画像の解像度が大きすぎます（6000万画素まで）。解像度を下げてからお試しください。';
    public const MESSAGE_ANIMATED_TOO_LARGE = 'アニメーション画像はアニメーションを保ったまま縮小できないため、5MB以下のファイルをアップロードしてください。';
    public const MESSAGE_CANNOT_FIT = '画像を自動で縮小しても5MB以下にできませんでした。解像度を下げた画像をお試しください。';
    public const MESSAGE_NO_OPTIMIZER = 'サーバーで画像を自動で縮小できないため、5MB以下の画像をアップロードしてください。';
    public const MESSAGE_FAILED = '画像の最適化に失敗しました。別の画像をお試しください。';

    private const IMAGICK_FORMATS = [
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/webp' => 'WEBP',
        'image/gif' => 'GIF',
    ];

    private const LOSSY_CANDIDATES = [
        [3840, 85], [3840, 80], [3840, 75],
        [3200, 75],
        [2560, 75], [2560, 70],
        [1920, 70], [1920, 65], [1920, 60],
    ];

    /** Long edges for lossless formats; 1600 is the hero size StageArtCore ships (1600x900). */
    private const LOSSLESS_LONG_EDGES = [3840, 3200, 2560, 1920, 1600];

    public function isSupported(string $mimeType): bool
    {
        return isset(self::IMAGICK_FORMATS[$mimeType]);
    }

    /** ImageMagick's name for the format, to compare with what it detects from the file's bytes. */
    public function imagickFormatFor(string $mimeType): ?string
    {
        return self::IMAGICK_FORMATS[$mimeType] ?? null;
    }

    public function exceedsReceivedLimit(int $bytes): bool
    {
        return $bytes > self::MAX_RECEIVED_BYTES;
    }

    public function exceedsStoredLimit(int $bytes): bool
    {
        return $bytes > self::MAX_STORED_BYTES;
    }

    public function exceedsPixelLimit(int $width, int $height): bool
    {
        return $width * $height > self::MAX_PIXELS;
    }

    /**
     * Re-encoding attempts for an image over the stored limit, in order.
     *
     * @return list<array{longEdge: int, quality: int|null}>
     */
    public function candidates(string $mimeType): array
    {
        if ($mimeType === 'image/jpeg' || $mimeType === 'image/webp') {
            return array_map(static fn (array $c): array => ['longEdge' => $c[0], 'quality' => $c[1]], self::LOSSY_CANDIDATES);
        }

        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            return array_map(static fn (int $edge): array => ['longEdge' => $edge, 'quality' => null], self::LOSSLESS_LONG_EDGES);
        }

        return [];
    }

    /**
     * Size that fits within a square of $longEdge, aspect kept, never larger
     * than the image itself.
     *
     * @return array{0: int, 1: int}
     */
    public function fitWithin(int $width, int $height, int $longEdge): array
    {
        $longest = max($width, $height);

        if ($longest <= $longEdge) {
            return [$width, $height];
        }

        $scale = $longEdge / $longest;

        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
    }
}
