<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Authentication\GoogleIdTokenClaims;
use StageArt\Application\Authentication\GoogleIdTokenVerifierInterface;
use StageArt\Application\Authentication\InvalidGoogleIdTokenException;

/**
 * Test double standing in for real Google ID Token signature/issuer/
 * audience verification (Infrastructure concern, not exercised by
 * Application-layer Use Case tests - see GoogleIdTokenVerifier's own
 * docblock). Register a fake "token string" -> claims mapping; any
 * unregistered token throws, matching the real verifier's contract.
 */
final class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    /** @var array<string, GoogleIdTokenClaims> */
    private array $validTokens = [];

    public function registerValidToken(
        string $idToken,
        string $sub,
        ?string $email = null,
        ?string $familyName = null,
        ?string $givenName = null
    ): void {
        $this->validTokens[$idToken] = new GoogleIdTokenClaims($sub, $email, $familyName, $givenName);
    }

    public function verify(string $idToken): GoogleIdTokenClaims
    {
        if (! isset($this->validTokens[$idToken])) {
            throw new InvalidGoogleIdTokenException("Unrecognized test ID Token: {$idToken}");
        }

        return $this->validTokens[$idToken];
    }
}
