<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\Authentication;

/**
 * Internal to the Infrastructure/Presentation boundary (see
 * CurrentUserResolver) - never passed into an Application-layer Use
 * Case. Deliberately does not carry wp_user_id (see JwtAccessTokenIssuer's
 * docblock for why it is never in the token payload at all).
 */
final class AccessTokenClaims
{
    public string $userAccountId;
    public string $personId;

    public function __construct(string $userAccountId, string $personId)
    {
        $this->userAccountId = $userAccountId;
        $this->personId = $personId;
    }
}
