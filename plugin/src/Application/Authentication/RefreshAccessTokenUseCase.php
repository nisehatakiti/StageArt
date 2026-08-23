<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

final class RefreshAccessTokenUseCase
{
    private RefreshTokenRepositoryInterface $refreshTokens;
    private UserAccountRepositoryInterface $userAccounts;
    private PersonRepositoryInterface $people;
    private AccessTokenIssuerInterface $accessTokenIssuer;

    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokens,
        UserAccountRepositoryInterface $userAccounts,
        PersonRepositoryInterface $people,
        AccessTokenIssuerInterface $accessTokenIssuer
    ) {
        $this->refreshTokens = $refreshTokens;
        $this->userAccounts = $userAccounts;
        $this->people = $people;
        $this->accessTokenIssuer = $accessTokenIssuer;
    }

    public function execute(RefreshAccessTokenCommand $command): RefreshAccessTokenResult
    {
        $hash = hash('sha256', $command->refreshToken);
        $refreshToken = $this->refreshTokens->findByTokenHash($hash);

        if (! $refreshToken || ! $refreshToken->isUsable()) {
            throw new InvalidRefreshTokenException('This Refresh Token is invalid, expired, or has been revoked.');
        }

        $userAccount = $this->userAccounts->findById($refreshToken->userAccountId());

        if (! $userAccount) {
            throw new InvalidRefreshTokenException('This Refresh Token is invalid, expired, or has been revoked.');
        }

        $person = $this->people->findById($userAccount->personId());
        $accessToken = $this->accessTokenIssuer->issue($userAccount->id(), $person->id());

        return new RefreshAccessTokenResult($accessToken->token, $accessToken->expiresInSeconds);
    }
}
