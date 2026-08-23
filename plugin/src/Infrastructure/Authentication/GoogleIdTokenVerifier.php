<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\Authentication;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use StageArt\Application\Authentication\GoogleIdTokenClaims;
use StageArt\Application\Authentication\GoogleIdTokenVerifierInterface;
use StageArt\Application\Authentication\InvalidGoogleIdTokenException;
use Throwable;

/**
 * Verifies issuer, audience, expiry, and signature (firebase/php-jwt
 * handles signature+expiry as part of JWT::decode(); issuer/audience are
 * checked explicitly below - the library does not do this for us). The
 * Web-type OAuth Client ID (not the iOS/Android Client ID) is what
 * Google puts in the `aud` claim of every ID Token it issues, regardless
 * of which platform the sign-in happened on - see this Phase's design
 * report §6.
 *
 * $webClientId is intentionally allowed to be an empty string: if the
 * environment constant (STAGEART_GOOGLE_WEB_CLIENT_ID, defined in
 * wp-config.php, never committed) is not configured, every token's `aud`
 * check fails closed rather than the plugin refusing to boot - Google
 * Sign-In simply stays unusable until configured, without taking down
 * any other unrelated feature.
 */
final class GoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    private const GOOGLE_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const JWKS_CACHE_KEY = 'stageart_google_jwks';
    private const JWKS_CACHE_TTL_SECONDS = 3600;

    private string $webClientId;

    public function __construct(string $webClientId)
    {
        $this->webClientId = $webClientId;
    }

    public function verify(string $idToken): GoogleIdTokenClaims
    {
        try {
            $keySet = JWK::parseKeySet($this->fetchJwks());
            $decoded = JWT::decode($idToken, $keySet);
        } catch (Throwable $exception) {
            throw new InvalidGoogleIdTokenException('Google ID Token verification failed.', 0, $exception);
        }

        $issuer = $decoded->iss ?? null;
        $audience = $decoded->aud ?? null;
        $subject = $decoded->sub ?? null;

        if (! is_string($issuer) || ! in_array($issuer, self::GOOGLE_ISSUERS, true)) {
            throw new InvalidGoogleIdTokenException('Google ID Token has an unexpected issuer.');
        }

        if ($this->webClientId === '' || $audience !== $this->webClientId) {
            throw new InvalidGoogleIdTokenException('Google ID Token has an unexpected audience.');
        }

        if (! is_string($subject) || trim($subject) === '') {
            throw new InvalidGoogleIdTokenException('Google ID Token has no subject.');
        }

        $email = isset($decoded->email) && is_string($decoded->email) ? $decoded->email : null;
        // StageArt Authentication Phase 6: family_name/given_name are
        // standard OIDC claims Google includes when the `profile` scope
        // was granted (the default Google Sign-In SDK scope set) - not
        // guaranteed present (see GoogleIdTokenClaims's own docblock),
        // so both fall back to null exactly like email above, never an
        // empty string.
        $familyName = isset($decoded->family_name) && is_string($decoded->family_name) ? $decoded->family_name : null;
        $givenName = isset($decoded->given_name) && is_string($decoded->given_name) ? $decoded->given_name : null;

        return new GoogleIdTokenClaims($subject, $email, $familyName, $givenName);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJwks(): array
    {
        $cached = get_transient(self::JWKS_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::JWKS_URL);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new InvalidGoogleIdTokenException('Unable to fetch Google\'s public keys.');
        }

        $jwks = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($jwks)) {
            throw new InvalidGoogleIdTokenException('Google\'s public key response was not valid JSON.');
        }

        set_transient(self::JWKS_CACHE_KEY, $jwks, self::JWKS_CACHE_TTL_SECONDS);

        return $jwks;
    }
}
