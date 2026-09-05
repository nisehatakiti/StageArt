<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

/**
 * StageArt Admin Console V1: a read-only projection of the fields the
 * Account Management screen needs from the underlying WordPress User
 * (email, display name) - never the whole WP_User object, so callers
 * cannot reach into WordPress-specific fields StageArt's Domain has no
 * business depending on.
 */
final class WordPressUserInfo
{
    public string $email;
    public string $displayName;

    public function __construct(string $email, string $displayName)
    {
        $this->email = $email;
        $this->displayName = $displayName;
    }
}
