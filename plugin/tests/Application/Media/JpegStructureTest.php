<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Media;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Media\JpegStructure;

final class JpegStructureTest extends TestCase
{
    private static function segment(int $marker, string $payload): string
    {
        return "\xFF" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
    }

    /** SOI, APP0, an APP1 holding an EXIF-thumbnail-like JPEG (with its own EOI), DQT, SOS + entropy data, EOI. */
    private static function jpeg(string $entropy = "\x12\x34\xFF\x00\x56\xFF\xD3\x78\x9A"): string
    {
        $thumbnail = "\xFF\xD8\xFF\xDA\x00\x02\x01\x02\xFF\xD9";

        return "\xFF\xD8"
            . self::segment(0xE0, "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00")
            . self::segment(0xE1, "Exif\x00\x00" . $thumbnail)
            . self::segment(0xDB, str_repeat("\x01", 65))
            . self::segment(0xDA, "\x01\x01\x00\x00\x3F\x00")
            . $entropy
            . "\xFF\xD9";
    }

    public function test_a_complete_jpeg_is_complete(): void
    {
        $this->assertTrue(JpegStructure::isComplete(self::jpeg()));
    }

    public function test_bytes_after_the_end_marker_are_allowed(): void
    {
        $this->assertTrue(JpegStructure::isComplete(self::jpeg() . 'motion-photo trailer'));
    }

    public function test_a_jpeg_cut_off_part_way_is_not_complete(): void
    {
        $full = self::jpeg();

        foreach ([0.3, 0.6, 0.9] as $fraction) {
            $this->assertFalse(JpegStructure::isComplete(substr($full, 0, (int) (strlen($full) * $fraction))));
        }
        $this->assertFalse(JpegStructure::isComplete(substr($full, 0, -2)));
    }

    public function test_the_end_marker_of_the_exif_thumbnail_does_not_count(): void
    {
        $full = self::jpeg();
        $cutInsideScan = strpos($full, "\x56");

        $this->assertStringContainsString("\xFF\xD9", substr($full, 0, $cutInsideScan));
        $this->assertFalse(JpegStructure::isComplete(substr($full, 0, $cutInsideScan)));
    }

    public function test_progressive_scans_are_followed_to_the_end(): void
    {
        // Two scans with a DHT between them (progressive JPEG layout).
        $entropy = "\x11\x22\xFF\x00" . self::segment(0xC4, "\x00\x01\x02") . self::segment(0xDA, "\x01\x01\x00\x00\x3F\x00") . "\x33\x44";

        $this->assertTrue(JpegStructure::isComplete(self::jpeg($entropy)));
    }

    public function test_other_data_is_not_a_jpeg(): void
    {
        $this->assertFalse(JpegStructure::isComplete(''));
        $this->assertFalse(JpegStructure::isComplete("this is plain text pretending to be a JPEG\n"));
        $this->assertFalse(JpegStructure::isComplete("\x89PNG\r\n\x1A\n"));
        $this->assertFalse(JpegStructure::isComplete("\xFF\xD8" . self::segment(0xE0, 'x') . 'garbage'));
    }

    public function test_a_real_jpeg_written_by_gd_is_complete_and_its_truncation_is_not(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD with JPEG support is not available here.');
        }

        $image = imagecreatetruecolor(320, 200);
        for ($x = 0; $x < 320; $x += 7) {
            imagefilledrectangle($image, $x, 0, $x + 3, 199, imagecolorallocate($image, $x % 256, 80, 200));
        }
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();

        $this->assertTrue(JpegStructure::isComplete($bytes));
        $this->assertFalse(JpegStructure::isComplete(substr($bytes, 0, (int) (strlen($bytes) * 0.6))));
    }
}
