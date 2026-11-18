<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\ParticipantInvitation;

use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\ParticipantInvitation\WordPressConfigEmailKeyring;

/**
 * PR #7 R14-B stage 8 (design doc §6.7): how the email_key keyring reads
 * STAGEART_EMAIL_KEYS / STAGEART_EMAIL_KEY_CURRENT - set, missing and
 * wrongly set. Anything unusable is "not configured", which makes invitation
 * create / resend refuse with 500 stageart_email_key_not_configured (no
 * unkeyed hash fallback). The constants are defined in separate processes.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressConfigEmailKeyringTest extends TestCase
{
    private const K1 = 'k1-0123456789abcdef0123456789abcdef';
    private const K2 = 'k2-0123456789abcdef0123456789abcdef';

    public function test_the_constants_give_the_current_version_and_every_listed_key(): void
    {
        define('STAGEART_EMAIL_KEYS', ['v1' => self::K1, 'v2' => self::K2]);
        define('STAGEART_EMAIL_KEY_CURRENT', 'v2');

        $keyring = new WordPressConfigEmailKeyring();

        $this->assertSame('v2', $keyring->currentVersion());
        $this->assertSame(self::K1, $keyring->keyFor('v1'), 'An older version stays usable for the 30-day count');
        $this->assertSame(self::K2, $keyring->keyFor('v2'));
        $this->assertNull($keyring->keyFor('v3'));
    }

    public function test_nothing_defined_is_not_configured(): void
    {
        $keyring = new WordPressConfigEmailKeyring();

        $this->assertNull($keyring->currentVersion());
        $this->assertNull($keyring->keyFor('v1'));
    }

    public function test_keys_that_are_not_an_array_are_not_configured(): void
    {
        define('STAGEART_EMAIL_KEYS', self::K1);
        define('STAGEART_EMAIL_KEY_CURRENT', 'v1');

        $this->assertNull((new WordPressConfigEmailKeyring())->currentVersion());
    }

    public function test_a_current_version_missing_from_the_keys_is_not_configured(): void
    {
        define('STAGEART_EMAIL_KEYS', ['v1' => self::K1]);
        define('STAGEART_EMAIL_KEY_CURRENT', 'v2');

        $keyring = new WordPressConfigEmailKeyring();

        $this->assertNull($keyring->currentVersion());
        $this->assertSame(self::K1, $keyring->keyFor('v1'), 'v1 can still count the window');
    }

    public function test_no_current_version_is_not_configured(): void
    {
        define('STAGEART_EMAIL_KEYS', ['v1' => self::K1]);

        $this->assertNull((new WordPressConfigEmailKeyring())->currentVersion());
    }

    public function test_a_key_shorter_than_32_bytes_or_a_bad_version_name_is_ignored(): void
    {
        define('STAGEART_EMAIL_KEYS', [
            'short' => str_repeat('x', 31),
            'exact' => str_repeat('y', 32),
            'bad version!' => self::K1,
            str_repeat('v', 21) => self::K1,
            'num' => 12345678901234567890123456789012,
        ]);
        define('STAGEART_EMAIL_KEY_CURRENT', 'short');

        $keyring = new WordPressConfigEmailKeyring();

        $this->assertNull($keyring->currentVersion(), 'The current version has no usable key');
        $this->assertNull($keyring->keyFor('short'));
        $this->assertSame(str_repeat('y', 32), $keyring->keyFor('exact'));
        $this->assertNull($keyring->keyFor('bad version!'));
        $this->assertNull($keyring->keyFor(str_repeat('v', 21)), 'email_key_version is VARCHAR(20)');
        $this->assertNull($keyring->keyFor('num'), 'A key must be a string');
    }
}
