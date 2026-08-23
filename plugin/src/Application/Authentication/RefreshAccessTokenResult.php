<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * Deliberately does not echo back a refresh_token: Phase 2 does not
 * rotate Refresh Tokens on use (see this Phase's design report §1) - the
 * client already holds the same Refresh Token it sent, so returning it
 * again would be redundant. A future Phase adding rotation would extend
 * this DTO then, not before.
 */
final class RefreshAccessTokenResult
{
    public string $accessToken;
    public int $expiresIn;

    public function __construct(string $accessToken, int $expiresIn)
    {
        $this->accessToken = $accessToken;
        $this->expiresIn = $expiresIn;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->expiresIn,
        ];
    }
}
