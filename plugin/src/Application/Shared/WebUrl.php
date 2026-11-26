<?php

declare(strict_types=1);

namespace StageArt\Application\Shared;

use InvalidArgumentException;

/**
 * PR #7 R14-A (F9): the one rule for a URL that StageArt stores and later
 * shows as a link or image on a page (organization external links, an
 * OTHER SNS profile URL, a Production flyer URL, an organization logo URL):
 * an absolute http:// or https:// URL with a host - nothing else.
 *
 * FILTER_VALIDATE_URL alone accepted `javascript://x/%0Aalert(1)`,
 * `data://…`, `vbscript://…`, `file:///…` and `intent://…`; the public
 * organization page opened such a value with Linking.openURL (window.open
 * on the web). The scheme is now checked explicitly, case-insensitively,
 * after trimming; control characters and whitespace inside the value are
 * refused (they are how a scheme is disguised, e.g. "java\tscript:").
 */
final class WebUrl
{
    private function __construct()
    {
    }

    /**
     * @return string the trimmed URL
     * @throws InvalidArgumentException when it is not an http(s) URL
     */
    public static function require(string $value, string $field): string
    {
        $url = trim($value);

        if (! self::isHttp($url)) {
            throw new InvalidArgumentException("{$field} must be an http:// or https:// URL.");
        }

        return $url;
    }

    /**
     * null and '' mean "no URL" and stay null; anything else must be an
     * http(s) URL.
     *
     * @throws InvalidArgumentException when it is not an http(s) URL
     */
    public static function optional(?string $value, string $field): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::require($value, $field);
    }

    public static function isHttp(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        if (preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }
}
