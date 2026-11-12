<?php

declare(strict_types=1);

namespace StageArt\Application\Media;

/**
 * Whether a JPEG file is complete: its segments are walked from SOI, the
 * entropy-coded data after each SOS is skipped (stuffed 0xFF00 bytes and
 * RSTn markers), and the image's own EOI must be reached. A file cut off
 * part-way has no EOI there.
 *
 * Needed because this server's ImageMagick (and GD even in strict mode)
 * decodes a truncated JPEG without an error and fills the missing part
 * grey. An EOI inside an APP1 segment (the EXIF thumbnail) is skipped with
 * that segment, so it does not count; bytes after the image's EOI (e.g. a
 * motion-photo trailer) are allowed.
 */
final class JpegStructure
{
    public static function isComplete(string $bytes): bool
    {
        $length = strlen($bytes);

        if ($length < 4 || $bytes[0] !== "\xFF" || $bytes[1] !== "\xD8") {
            return false;
        }

        $position = 2;

        while ($position < $length) {
            if ($bytes[$position] !== "\xFF") {
                return false;
            }

            // Fill bytes before a marker.
            while ($position < $length && $bytes[$position] === "\xFF") {
                $position++;
            }

            if ($position >= $length) {
                return false;
            }

            $marker = ord($bytes[$position]);
            $position++;

            if ($marker === 0xD9) {
                return true;
            }

            // Markers without a length field.
            if (($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                continue;
            }

            if ($position + 2 > $length) {
                return false;
            }

            $segmentLength = (ord($bytes[$position]) << 8) | ord($bytes[$position + 1]);

            if ($segmentLength < 2 || $position + $segmentLength > $length) {
                return false;
            }

            $position += $segmentLength;

            if ($marker !== 0xDA) {
                continue;
            }

            // Entropy-coded data up to the next real marker.
            while (true) {
                $next = strpos($bytes, "\xFF", $position);

                if ($next === false || $next + 1 >= $length) {
                    return false;
                }

                $following = ord($bytes[$next + 1]);

                if ($following === 0x00 || ($following >= 0xD0 && $following <= 0xD7) || $following === 0xFF) {
                    $position = $next + ($following === 0xFF ? 1 : 2);

                    continue;
                }

                $position = $next;

                break;
            }
        }

        return false;
    }
}
