<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

/**
 * 通知用Email確認・変更機能 §3: `PersonEmailResolver::resolve()` only
 * ever needed the final email string for Email delivery - but Settings
 * display needs to know WHICH source it came from too (仕様書's Case
 * A/B/D), specifically so it can avoid implying a plain EmailCredential/
 * WordPress-user fallback (Case B) is a saved `NotificationEmail`
 * (Case A) when it never was one. This is a read-only query result, not
 * a persisted Domain concept - it lives in Application, not Domain.
 */
final class PersonEmailResolution
{
    public const SOURCE_NOTIFICATION_EMAIL = 'NOTIFICATION_EMAIL';
    public const SOURCE_EMAIL_CREDENTIAL = 'EMAIL_CREDENTIAL';
    public const SOURCE_WORDPRESS_USER = 'WORDPRESS_USER';
    public const SOURCE_NONE = 'NONE';

    public ?string $email;
    public string $source;

    public function __construct(?string $email, string $source)
    {
        $this->email = $email;
        $this->source = $source;
    }
}
