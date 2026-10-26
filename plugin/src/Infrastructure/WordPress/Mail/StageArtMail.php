<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Mail;

/**
 * Every StageArt system email (verification, password reset, invitations,
 * questionnaire requests, notifications incl. rehearsal reminders) is sent
 * through here, so they all show one sender: `StageArt <noreply@stageart.top>`
 * instead of WordPress's default "WordPress <wordpress@{site host}>".
 *
 * The sender is set with a `From:` header on StageArt's own messages only;
 * other email the WordPress site sends (core, other plugins) is left as is.
 * The name/address can be changed per environment in wp-config.php with
 * STAGEART_MAIL_FROM_NAME / STAGEART_MAIL_FROM_ADDRESS. No Reply-To is
 * added (none existed). Delivery itself is still wp_mail()'s: transport,
 * envelope sender and any SMTP setup are unchanged.
 */
final class StageArtMail
{
    public const DEFAULT_FROM_NAME = 'StageArt';
    public const DEFAULT_FROM_ADDRESS = 'noreply@stageart.top';

    /**
     * @param string[] $headers extra headers (e.g. Content-Type), as wp_mail() takes them
     */
    public static function send(string $to, string $subject, string $message, array $headers = []): bool
    {
        return wp_mail($to, $subject, $message, array_merge([self::fromHeader()], $headers));
    }

    public static function fromName(): string
    {
        $name = defined('STAGEART_MAIL_FROM_NAME') ? (string) constant('STAGEART_MAIL_FROM_NAME') : '';

        // A header value: no line breaks.
        $name = trim(str_replace(["\r", "\n"], '', $name));

        return $name !== '' ? $name : self::DEFAULT_FROM_NAME;
    }

    public static function fromAddress(): string
    {
        $address = defined('STAGEART_MAIL_FROM_ADDRESS') ? trim((string) constant('STAGEART_MAIL_FROM_ADDRESS')) : '';

        return $address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false ? $address : self::DEFAULT_FROM_ADDRESS;
    }

    public static function fromHeader(): string
    {
        return sprintf('From: %s <%s>', self::fromName(), self::fromAddress());
    }
}
