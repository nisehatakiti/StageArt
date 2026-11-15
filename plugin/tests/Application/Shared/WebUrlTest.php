<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Shared;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Shared\WebUrl;

/**
 * PR #7 R14-A (F9): only absolute http(s) URLs with a host are stored for
 * links and images shown on pages.
 */
final class WebUrlTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function allowed(): array
    {
        return [
            'https' => ['https://example.com'],
            'http with path and query' => ['http://example.com/path?a=1&b=2#top'],
            'upper-case scheme' => ['HTTPS://EXAMPLE.COM/'],
            'with port' => ['https://example.com:8443/x'],
            'international domain (punycode)' => ['https://xn--eckwd4c7c.xn--zckzah/'],
            'percent-encoded path' => ['https://example.com/%E5%85%AC%E6%BC%94'],
        ];
    }

    /**
     * @dataProvider allowed
     */
    public function test_http_and_https_urls_are_accepted(string $url): void
    {
        $this->assertTrue(WebUrl::isHttp($url));
        $this->assertSame($url, WebUrl::require('  ' . $url . "\n", 'url'), 'surrounding whitespace is trimmed');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refused(): array
    {
        return [
            'javascript with slashes (passed FILTER_VALIDATE_URL)' => ['javascript://x/%0Aalert(document.domain)'],
            'javascript' => ['javascript:alert(1)'],
            'upper-case javascript' => ['JAVASCRIPT://x/%0Aalert(1)'],
            'data with slashes (passed FILTER_VALIDATE_URL)' => ['data://text/html,abc'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript with slashes (passed FILTER_VALIDATE_URL)' => ['vbscript://x/msgbox(1)'],
            'file (passed FILTER_VALIDATE_URL)' => ['file:///etc/passwd'],
            'intent (passed FILTER_VALIDATE_URL)' => ['intent://x#Intent;end'],
            'ftp' => ['ftp://example.com/file'],
            'mailto' => ['mailto:someone@example.com'],
            'scheme-relative' => ['//example.com/x'],
            'relative path' => ['/wp-content/uploads/logo.png'],
            'tab inside the scheme' => ["java\tscript://x/%0Aalert(1)"],
            'tab inside an http URL' => ["https://exa\tmple.com"],
            'space inside' => ['https://example .com'],
            'newline inside' => ["https://example.com/\nx"],
            'NUL byte inside (a trailing one is trimmed away)' => ["https://exa\0mple.com/"],
            'encoded scheme' => ['%6Aavascript://x/%0Aalert(1)'],
            'no host' => ['https://'],
            'http without slashes' => ['http:example.com'],
            'empty' => [''],
        ];
    }

    /**
     * @dataProvider refused
     */
    public function test_other_schemes_and_disguised_values_are_refused(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        WebUrl::require($url, 'url');
    }

    public function test_optional_keeps_no_url_as_null(): void
    {
        $this->assertNull(WebUrl::optional(null, 'logo_url'));
        $this->assertNull(WebUrl::optional('', 'logo_url'));
        $this->assertNull(WebUrl::optional('   ', 'logo_url'));
        $this->assertSame('https://example.com/a.png', WebUrl::optional('https://example.com/a.png', 'logo_url'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('logo_url must be an http:// or https:// URL.');
        WebUrl::optional('javascript://x/%0Aalert(1)', 'logo_url');
    }
}
