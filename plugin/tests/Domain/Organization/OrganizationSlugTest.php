<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Organization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Organization\OrganizationSlug;

final class OrganizationSlugTest extends TestCase
{
    public function test_accepts_a_valid_lowercase_hyphenated_slug(): void
    {
        $slug = new OrganizationSlug('gekidan-example');

        $this->assertSame('gekidan-example', $slug->toString());
    }

    public function test_rejects_uppercase(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationSlug('Gekidan-Example');
    }

    public function test_rejects_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationSlug('ab');
    }

    public function test_rejects_leading_hyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationSlug('-gekidan');
    }

    public function test_rejects_trailing_hyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationSlug('gekidan-');
    }

    public function test_rejects_double_hyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationSlug('geki--dan');
    }

    public function test_rejects_non_ascii_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationSlug('劇団');
    }

    public function test_rejects_a_reserved_word(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationSlug('admin');
    }

    /**
     * Organization slugs now resolve at the Web app's URL root
     * (`/{organization-slug}`), so any slug matching a real top-level
     * route name would otherwise shadow that route forever.
     */
    public function test_rejects_slugs_matching_web_app_route_names(): void
    {
        foreach (['login', 'home', 'join', 'discover-organizations', 'production'] as $routeName) {
            try {
                new OrganizationSlug($routeName);
                $this->fail("Expected \"{$routeName}\" to be rejected as a reserved slug.");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('reserved', $exception->getMessage());
            }
        }
    }

    public function test_equals_compares_by_value(): void
    {
        $first = new OrganizationSlug('gekidan-example');
        $second = new OrganizationSlug('gekidan-example');
        $different = new OrganizationSlug('other-theatre');

        $this->assertTrue($first->equals($second));
        $this->assertFalse($first->equals($different));
    }
}
