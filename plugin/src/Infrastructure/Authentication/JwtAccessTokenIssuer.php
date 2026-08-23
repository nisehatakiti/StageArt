<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\Authentication;

use Firebase\JWT\JWT;
use StageArt\Application\Authentication\AccessTokenIssuerInterface;
use StageArt\Application\Authentication\AccessTokenResult;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\UserAccountId;

/**
 * Issues StageArt's own Access Token - a short-lived, stateless,
 * HMAC-signed JWT verified entirely by signature (no DB lookup needed
 * per request, see JwtAccessTokenVerifier). Deliberately carries only
 * `user_account_id`/`person_id` - never `wp_user_id`: even though a JWT
 * payload is merely signed, not encrypted (a determined reader could
 * decode it), WordPress User is still not put in it, matching both the
 * letter and spirit of "StageArtユーザーには絶対に露出しない" (this
 * Phase's final policy) rather than relying on "nobody will decode it"
 * as the reason it is not exposed - see this Phase's design report §1.
 */
final class JwtAccessTokenIssuer implements AccessTokenIssuerInterface
{
    private const ALGORITHM = 'HS256';
    public const LIFETIME_SECONDS = 3600;

    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function issue(UserAccountId $userAccountId, PersonId $personId): AccessTokenResult
    {
        $now = time();
        $expiresAt = $now + self::LIFETIME_SECONDS;

        $payload = [
            'sub' => $userAccountId->toString(),
            'person_id' => $personId->toString(),
            'iat' => $now,
            'exp' => $expiresAt,
        ];

        $token = JWT::encode($payload, $this->secret, self::ALGORITHM);

        return new AccessTokenResult($token, self::LIFETIME_SECONDS);
    }
}
