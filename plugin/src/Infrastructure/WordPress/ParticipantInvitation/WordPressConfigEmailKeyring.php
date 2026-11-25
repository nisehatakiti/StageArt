<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\ParticipantInvitation;

use StageArt\Application\ParticipantInvitation\EmailKeyringInterface;

/**
 * R14-B §6.7 (stage 4): the email_key keyring from wp-config.php.
 *
 *   define('STAGEART_EMAIL_KEYS', ['v1' => '<at least 32 random bytes>', ...]);
 *   define('STAGEART_EMAIL_KEY_CURRENT', 'v1');
 *
 * A version name is 1-20 characters of [A-Za-z0-9._-] (it is stored in
 * email_key_version VARCHAR(20)); a key shorter than 32 bytes is treated as
 * missing. Anything missing makes the caller refuse the send (safe side) -
 * there is no fallback to an unkeyed hash. Separate from
 * STAGEART_JWT_SECRET. Key values never leave this object except to compute
 * the HMAC.
 */
final class WordPressConfigEmailKeyring implements EmailKeyringInterface
{
    private const MIN_KEY_BYTES = 32;

    /** @var array<string, string> */
    private array $keys;
    private ?string $current;

    /**
     * @param array<string, string>|null $keys    null = read STAGEART_EMAIL_KEYS
     * @param string|null                $current null = read STAGEART_EMAIL_KEY_CURRENT
     */
    public function __construct(?array $keys = null, ?string $current = null, bool $fromConstants = true)
    {
        if ($fromConstants && $keys === null) {
            $keys = defined('STAGEART_EMAIL_KEYS') && is_array(constant('STAGEART_EMAIL_KEYS')) ? constant('STAGEART_EMAIL_KEYS') : [];
        }

        if ($fromConstants && $current === null) {
            $current = defined('STAGEART_EMAIL_KEY_CURRENT') ? (string) constant('STAGEART_EMAIL_KEY_CURRENT') : null;
        }

        $this->keys = [];

        foreach ($keys ?? [] as $version => $key) {
            if (self::isUsableVersion((string) $version) && is_string($key) && strlen($key) >= self::MIN_KEY_BYTES) {
                $this->keys[(string) $version] = $key;
            }
        }

        $this->current = $current;
    }

    public function currentVersion(): ?string
    {
        if ($this->current === null || ! isset($this->keys[$this->current])) {
            return null;
        }

        return $this->current;
    }

    public function keyFor(string $version): ?string
    {
        return $this->keys[$version] ?? null;
    }

    private static function isUsableVersion(string $version): bool
    {
        return preg_match('/^[A-Za-z0-9._-]{1,20}$/', $version) === 1;
    }
}
