<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Production;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Production\ProductionSlug;

final class ProductionSlugTest extends TestCase
{
    public function test_accepts_a_valid_lowercase_hyphenated_slug(): void
    {
        $slug = new ProductionSlug('example-production');

        $this->assertSame('example-production', $slug->toString());
    }

    public function test_rejects_uppercase(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProductionSlug('Example-Production');
    }

    public function test_rejects_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProductionSlug('ab');
    }

    public function test_rejects_leading_or_trailing_hyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProductionSlug('-example');
    }

    public function test_rejects_non_ascii_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProductionSlug('公演');
    }

    public function test_rejects_a_reserved_word(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProductionSlug('api');
    }

    public function test_equals_compares_by_value(): void
    {
        $first = new ProductionSlug('example-production');
        $second = new ProductionSlug('example-production');
        $different = new ProductionSlug('other-production');

        $this->assertTrue($first->equals($second));
        $this->assertFalse($first->equals($different));
    }
}
