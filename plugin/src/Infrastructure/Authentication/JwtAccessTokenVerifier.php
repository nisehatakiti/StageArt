<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\Authentication;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Verifies a StageArt Access Token issued by JwtAccessTokenIssuer.
 * Deliberately returns null rather than throwing on any failure
 * (malformed input, wrong signature, expired) - this runs on every
 * single incoming request (see CurrentUserResolver), including requests
 * with no Bearer token at all or an unrelated Authorization header
 * (Basic Auth), so "not a valid StageArt Access Token" must be a normal,
 * silent outcome, not an exception to catch everywhere it is called.
 */
final class JwtAccessTokenVerifier
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function verify(string $token): ?AccessTokenClaims
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (Throwable $exception) {
            return null;
        }

        $userAccountId = $decoded->sub ?? null;
        $personId = $decoded->person_id ?? null;

        if (! is_string($userAccountId) || ! is_string($personId) || $userAccountId === '' || $personId === '') {
            return null;
        }

        return new AccessTokenClaims($userAccountId, $personId);
    }
}
