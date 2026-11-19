<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Media;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Media\ImageOptimizationPolicy;

final class ImageOptimizationPolicyTest extends TestCase
{
    private ImageOptimizationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ImageOptimizationPolicy();
    }

    public function test_up_to_10mb_is_received_and_up_to_5mb_is_stored_as_uploaded(): void
    {
        $this->assertFalse($this->policy->exceedsReceivedLimit(10 * 1024 * 1024));
        $this->assertTrue($this->policy->exceedsReceivedLimit(10 * 1024 * 1024 + 1));

        $this->assertFalse($this->policy->exceedsStoredLimit(5 * 1024 * 1024));
        $this->assertTrue($this->policy->exceedsStoredLimit(5 * 1024 * 1024 + 1));
    }

    public function test_only_the_four_image_formats_are_supported(): void
    {
        foreach (['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/webp' => 'WEBP', 'image/gif' => 'GIF'] as $mime => $format) {
            $this->assertTrue($this->policy->isSupported($mime));
            $this->assertSame($format, $this->policy->imagickFormatFor($mime));
        }

        $this->assertFalse($this->policy->isSupported('image/heic'));
        $this->assertFalse($this->policy->isSupported('text/plain'));
        $this->assertSame([], $this->policy->candidates('image/heic'));
    }

    public function test_jpeg_and_webp_start_at_quality_85_and_step_down(): void
    {
        foreach (['image/jpeg', 'image/webp'] as $mime) {
            $candidates = $this->policy->candidates($mime);

            $this->assertSame(['longEdge' => 3840, 'quality' => 85], $candidates[0]);
            $qualities = array_column($candidates, 'quality');
            $edges = array_column($candidates, 'longEdge');
            $this->assertSame($qualities, array_values(array_filter($qualities, static fn (int $q): bool => $q >= 60 && $q <= 85)));
            // Never goes back up: each step is smaller or lower quality.
            for ($i = 1; $i < count($candidates); $i++) {
                $this->assertTrue($edges[$i] < $edges[$i - 1] || $qualities[$i] < $qualities[$i - 1]);
                $this->assertLessThanOrEqual($edges[$i - 1], $edges[$i]);
            }
        }
    }

    public function test_png_and_gif_are_only_scaled_down_never_given_a_quality(): void
    {
        foreach (['image/png', 'image/gif'] as $mime) {
            $candidates = $this->policy->candidates($mime);

            $this->assertSame([3840, 3200, 2560, 1920, 1600], array_column($candidates, 'longEdge'));
            $this->assertSame([null, null, null, null, null], array_column($candidates, 'quality'));
        }
    }

    public function test_fit_within_keeps_the_aspect_ratio_and_never_enlarges(): void
    {
        $this->assertSame([3840, 2880], $this->policy->fitWithin(6000, 4500, 3840));
        $this->assertSame([2880, 3840], $this->policy->fitWithin(4500, 6000, 3840));
        $this->assertSame([1600, 900], $this->policy->fitWithin(1600, 900, 3840));
        $this->assertSame([1920, 1], $this->policy->fitWithin(10000, 2, 1920));
    }

    public function test_pixel_limit_is_60_megapixels(): void
    {
        $this->assertFalse($this->policy->exceedsPixelLimit(8160, 6120)); // 50MP phone
        $this->assertTrue($this->policy->exceedsPixelLimit(10000, 6001));
    }
}
