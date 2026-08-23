<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * StageArt Authentication Phase 6: familyNameHint/givenNameHint are
 * additive, always-nullable fields - only AuthenticateWithGoogleUseCase
 * ever populates them (from GoogleIdTokenClaims, when Google's own ID
 * Token happened to carry them), every other flow (Email register/
 * login/refresh) passes null. These are UI hints only, for mobile-rn's
 * set-name.tsx default values - they are never written to Person
 * automatically, so their presence or absence here has no bearing on
 * the caller's actual stored name (see GET /me's family_name/given_name
 * for that).
 */
final class AuthenticationResult
{
    public string $accessToken;
    public string $refreshToken;
    public int $expiresIn;
    public string $personId;
    public string $userAccountId;
    public bool $isNewUser;
    public ?string $familyNameHint;
    public ?string $givenNameHint;

    public function __construct(
        string $accessToken,
        string $refreshToken,
        int $expiresIn,
        string $personId,
        string $userAccountId,
        bool $isNewUser,
        ?string $familyNameHint = null,
        ?string $givenNameHint = null
    ) {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresIn = $expiresIn;
        $this->personId = $personId;
        $this->userAccountId = $userAccountId;
        $this->isNewUser = $isNewUser;
        $this->familyNameHint = $familyNameHint;
        $this->givenNameHint = $givenNameHint;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->expiresIn,
            'person_id' => $this->personId,
            'user_account_id' => $this->userAccountId,
            'is_new_user' => $this->isNewUser,
            'family_name_hint' => $this->familyNameHint,
            'given_name_hint' => $this->givenNameHint,
        ];
    }
}
