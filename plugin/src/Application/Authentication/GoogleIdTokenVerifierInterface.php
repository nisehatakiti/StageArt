<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * Port for verifying a Google ID Token (issuer/audience/expiry/signature)
 * - per UserAccount.md's "外部Provider固有の認証処理はInfrastructure
 * Layerが担当する。Domain Layerは特定Authentication ProviderのAPIへ直接
 * 依存しない". The Application layer only ever sees this interface;
 * Google's own SDKs/JWKS fetching/etc. are entirely behind the
 * Infrastructure implementation.
 */
interface GoogleIdTokenVerifierInterface
{
    /**
     * @throws InvalidGoogleIdTokenException if the token fails any check.
     */
    public function verify(string $idToken): GoogleIdTokenClaims;
}
