<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Authentication;

use StageArt\Application\Admin\AdminConsoleAccountCreationException;
use StageArt\Application\Admin\AdminConsoleAccountProvisionerInterface;

/**
 * Mirrors WordPressUserProvisioner's shape (also in this namespace) but
 * for the opposite purpose: that class provisions a hidden, StageArt-
 * Person-linked WordPress User a real end user never sees or logs into
 * directly; this one creates a real, visible WordPress User an Admin
 * Console administrator DOES log into directly with the username/
 * password they were just given - the 'stageart_admin' role (see
 * Installer::installAdminConsoleRole()) is what actually grants Admin
 * Console access, not any StageArt Domain concept.
 */
final class WordPressAdminConsoleAccountProvisioner implements AdminConsoleAccountProvisionerInterface
{
    private const ROLE = 'stageart_admin';

    public function provision(string $username, string $email, string $password): void
    {
        $userId = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'role' => self::ROLE,
        ]);

        if (is_wp_error($userId)) {
            throw new AdminConsoleAccountCreationException($userId->get_error_message());
        }
    }
}
