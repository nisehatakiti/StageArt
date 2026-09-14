<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Notification;

use StageArt\Application\Notification\NotificationDeliveryAdapterInterface;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Domain\Person\PersonId;

/**
 * Notification基盤実装 phase §5: uses `wp_mail()` only, the same
 * already-available WordPress core mechanism `WordPressAuthMailer`
 * already uses for password-reset/email-verification - no new
 * dependency, no external SMTP/provider decision needed to at least
 * attempt delivery through whatever mail transport the site's own
 * WordPress install is already configured with (default PHP `mail()`,
 * or any SMTP plugin the site operator has set up - this Adapter is
 * agnostic to that, same as `WordPressAuthMailer`).
 *
 * Silently skips (no exception, no log) a Person `PersonEmailResolver`
 * cannot find a real address for - this is an expected, common case
 * (a Google-only account with no EmailCredential), not an error.
 */
final class WordPressEmailNotificationAdapter implements NotificationDeliveryAdapterInterface
{
    private PersonEmailResolver $emailResolver;

    public function __construct(PersonEmailResolver $emailResolver)
    {
        $this->emailResolver = $emailResolver;
    }

    public function deliver(PersonId $personId, string $type, array $payload): void
    {
        $email = $this->emailResolver->resolve($personId);

        if ($email === null) {
            return;
        }

        $message = is_string($payload['message'] ?? null) ? $payload['message'] : 'StageArtからのお知らせがあります。';

        wp_mail($email, __('StageArt - お知らせ', 'stageart'), $message);
    }
}
