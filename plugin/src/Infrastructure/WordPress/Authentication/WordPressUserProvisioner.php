<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Authentication;

use RuntimeException;
use StageArt\Application\Authentication\WordPressUserProvisionerInterface;

/**
 * Auto-provisions a hidden WordPress User for a new Google-authenticated
 * Person (this Phase's design report §2). The generated username and
 * password are discarded immediately after wp_insert_user() returns -
 * neither is ever logged, returned, or reachable by any caller. Nothing
 * about this User is ever surfaced to the StageArt user; it exists only
 * to satisfy Person.wordPressUserId's current (see design report §3)
 * Infrastructure requirement.
 *
 * user_email always uses the synthetic `.invalid` TLD (RFC 2606:
 * guaranteed never to resolve to a real domain) rather than the real
 * Google email - this sidesteps any risk of colliding with an unrelated
 * existing WordPress User's email (which would make wp_insert_user()
 * fail) and keeps this WordPress User's own email column a pure
 * technical placeholder, never a real contact channel.
 */
final class WordPressUserProvisioner implements WordPressUserProvisionerInterface
{
    public function provision(?string $googleEmail): int
    {
        $suffix = bin2hex(random_bytes(16));

        $userId = wp_insert_user([
            'user_login' => "stageart_google_{$suffix}",
            'user_email' => "{$suffix}@users.stageart.invalid",
            'user_pass' => wp_generate_password(32, true, true),
            'display_name' => 'StageArt User',
            'role' => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Failed to provision an internal WordPress User: ' . $userId->get_error_message());
        }

        return (int) $userId;
    }
}
