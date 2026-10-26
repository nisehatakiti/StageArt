<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationLogoResizePolicy;

/**
 * The WordPress-free rules behind Organization logo shrinking. The pixel
 * work itself (WordPress's image editor) is outside this Domain/
 * Application-only test tier - see WordPressOrganizationLogoShrinker.
 */
final class OrganizationLogoResizePolicyTest extends TestCase
{
    public function test_default_long_edge_limit_is_1024px(): void
    {
        $this->assertSame(1024, (new OrganizationLogoResizePolicy())->maxLongEdge());
        $this->assertSame(1024, OrganizationLogoResizePolicy::DEFAULT_MAX_LONG_EDGE_PX);
    }

    public function test_limit_is_configurable_but_must_be_positive(): void
    {
        $this->assertSame(512, (new OrganizationLogoResizePolicy(512))->maxLongEdge());

        $this->expectException(InvalidArgumentException::class);
        new OrganizationLogoResizePolicy(0);
    }

    /** @dataProvider resizableFormats */
    public function test_jpeg_png_and_webp_are_resizable(string $mimeType, string $extension): void
    {
        $policy = new OrganizationLogoResizePolicy();

        $this->assertTrue($policy->isResizableMimeType($mimeType));
        $this->assertSame($extension, $policy->extensionFor($mimeType));
    }

    /** @return array<string, array{string, string}> */
    public static function resizableFormats(): array
    {
        return [
            'JPEG' => ['image/jpeg', 'jpg'],
            'PNG' => ['image/png', 'png'],
            'WebP' => ['image/webp', 'webp'],
        ];
    }

    public function test_gif_is_never_resized_even_when_large(): void
    {
        $policy = new OrganizationLogoResizePolicy();

        $this->assertFalse($policy->isResizableMimeType('image/gif'));
        $this->assertFalse($policy->needsResize('image/gif', 4000, 3000));

        $this->expectException(InvalidArgumentException::class);
        $policy->extensionFor('image/gif');
    }

    /** @dataProvider sizes */
    public function test_only_images_whose_long_edge_exceeds_the_limit_are_resized(int $width, int $height, bool $expected): void
    {
        $this->assertSame($expected, (new OrganizationLogoResizePolicy())->needsResize('image/png', $width, $height));
    }

    /** @return array<string, array{int, int, bool}> */
    public static function sizes(): array
    {
        return [
            'small' => [800, 600, false],
            'exactly the limit (wide)' => [1024, 512, false],
            'exactly the limit (tall)' => [300, 1024, false],
            'one pixel over (wide)' => [1025, 100, true],
            'one pixel over (tall)' => [100, 1025, true],
            'large square' => [2000, 2000, true],
        ];
    }

    /** @dataProvider acceptableOutputs */
    public function test_accepts_a_proportional_downscale_within_the_limit(int $sw, int $sh, int $ow, int $oh): void
    {
        $this->assertTrue((new OrganizationLogoResizePolicy())->isAcceptableOutput('image/png', $sw, $sh, 'image/png', $ow, $oh));
    }

    /** @return array<string, array{int, int, int, int}> */
    public static function acceptableOutputs(): array
    {
        return [
            'square' => [2000, 2000, 1024, 1024],
            'wide 4:1' => [3000, 750, 1024, 256],
            'tall 1:4' => [600, 2400, 256, 1024],
            'odd ratio rounded to the nearest pixel' => [3001, 1000, 1024, 341],
        ];
    }

    /** @dataProvider rejectedOutputs */
    public function test_rejects_outputs_that_do_not_match_what_was_asked_for(string $outMime, int $ow, int $oh): void
    {
        // source: 3000x750 PNG (4:1)
        $this->assertFalse((new OrganizationLogoResizePolicy())->isAcceptableOutput('image/png', 3000, 750, $outMime, $ow, $oh));
    }

    /** @return array<string, array{string, int, int}> */
    public static function rejectedOutputs(): array
    {
        return [
            'different format' => ['image/jpeg', 1024, 256],
            'long edge over the limit' => ['image/png', 1500, 375],
            'cropped to a square' => ['image/png', 1024, 1024],
            'stretched' => ['image/png', 1024, 512],
            'empty' => ['image/png', 0, 0],
        ];
    }

    public function test_rejects_an_output_larger_than_its_source(): void
    {
        $this->assertFalse((new OrganizationLogoResizePolicy(2048))->isAcceptableOutput('image/jpeg', 1200, 600, 'image/jpeg', 2048, 1024));
    }

    public function test_a_complete_jpeg_is_accepted_and_one_cut_off_part_way_is_not(): void
    {
        $policy = new OrganizationLogoResizePolicy();
        // SOI, DQT, SOS + entropy data (stuffed byte, RST marker), EOI.
        $jpeg = "\xFF\xD8"
            . "\xFF\xDB" . pack('n', 67) . str_repeat("\x01", 65)
            . "\xFF\xDA" . pack('n', 8) . "\x01\x01\x00\x00\x3F\x00"
            . "\x12\x34\xFF\x00\x56\xFF\xD3\x78\x9A"
            . "\xFF\xD9";

        $this->assertTrue($policy->isCompleteFile('image/jpeg', $jpeg));
        $this->assertFalse($policy->isCompleteFile('image/jpeg', substr($jpeg, 0, strlen($jpeg) - 2)));
        $this->assertFalse($policy->isCompleteFile('image/jpeg', substr($jpeg, 0, 40)));
        $this->assertFalse($policy->isCompleteFile('image/jpeg', 'not a jpeg'));
    }

    public function test_a_small_real_jpeg_cut_off_part_way_is_rejected(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD with JPEG support is not available here.');
        }

        // Under the 1024px limit, so it would be stored without resizing.
        $image = imagecreatetruecolor(400, 100);
        for ($x = 0; $x < 400; $x += 9) {
            imagefilledrectangle($image, $x, 0, $x + 4, 99, imagecolorallocate($image, $x % 256, 120, 60));
        }
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        $policy = new OrganizationLogoResizePolicy();

        $this->assertFalse($policy->needsResize('image/jpeg', 400, 100));
        $this->assertTrue($policy->isCompleteFile('image/jpeg', $bytes));
        $this->assertFalse($policy->isCompleteFile('image/jpeg', substr($bytes, 0, (int) (strlen($bytes) * 0.7))));
    }

    /** @dataProvider uncheckedFormats */
    public function test_other_formats_are_not_checked_here(string $mimeType): void
    {
        $this->assertTrue((new OrganizationLogoResizePolicy())->isCompleteFile($mimeType, 'any bytes'));
    }

    /** @return array<string, array{0: string}> */
    public static function uncheckedFormats(): array
    {
        return ['png' => ['image/png'], 'webp' => ['image/webp'], 'gif' => ['image/gif']];
    }
}
