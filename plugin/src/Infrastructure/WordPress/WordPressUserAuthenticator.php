<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress;

use StageArt\Application\Auth\AuthenticatedUser;
use StageArt\Application\Auth\UserAuthenticatorInterface;

final class WordPressUserAuthenticator implements UserAuthenticatorInterface
{
    public function authenticate(string $email, string $password): ?AuthenticatedUser
    {
        $user = wp_signon([
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            return null;
        }

        return new AuthenticatedUser(
            $user->ID,
            $user->user_email,
            $user->display_name
        );
    }
}
