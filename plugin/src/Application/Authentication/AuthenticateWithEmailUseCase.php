<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Authentication\RefreshToken;
use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Email+Password login for an existing EmailCredential. password_verify()
 * runs before the transaction (a pure check, no side effects to roll
 * back), matching AuthenticateWithGoogleUseCase's Google-verification-
 * before-transaction structure. Issues the same Access/Refresh Token pair
 * via the same provider-agnostic AccessTokenIssuerInterface/
 * RefreshTokenRepositoryInterface Google authentication uses.
 */
final class AuthenticateWithEmailUseCase
{
    private const REFRESH_TOKEN_LIFETIME = 'P30D';

    private EmailCredentialRepositoryInterface $emailCredentials;
    private UserAccountRepositoryInterface $userAccounts;
    private PersonRepositoryInterface $people;
    private RefreshTokenRepositoryInterface $refreshTokens;
    private AccessTokenIssuerInterface $accessTokenIssuer;
    private TransactionManagerInterface $transactions;

    public function __construct(
        EmailCredentialRepositoryInterface $emailCredentials,
        UserAccountRepositoryInterface $userAccounts,
        PersonRepositoryInterface $people,
        RefreshTokenRepositoryInterface $refreshTokens,
        AccessTokenIssuerInterface $accessTokenIssuer,
        TransactionManagerInterface $transactions
    ) {
        $this->emailCredentials = $emailCredentials;
        $this->userAccounts = $userAccounts;
        $this->people = $people;
        $this->refreshTokens = $refreshTokens;
        $this->accessTokenIssuer = $accessTokenIssuer;
        $this->transactions = $transactions;
    }

    public function execute(AuthenticateWithEmailCommand $command): AuthenticationResult
    {
        $credential = $this->emailCredentials->findByEmail($command->email);

        if (! $credential || ! password_verify($command->password, $credential->passwordHash())) {
            throw new InvalidCredentialsException('The email address or password is incorrect.');
        }

        return $this->transactions->run(function () use ($credential): AuthenticationResult {
            $userAccount = $this->userAccounts->findById($credential->userAccountId());
            $person = $this->people->findById($userAccount->personId());

            $accessToken = $this->accessTokenIssuer->issue($userAccount->id(), $person->id());
            $refreshTokenValue = bin2hex(random_bytes(32));
            $refreshTokenHash = hash('sha256', $refreshTokenValue);

            $refreshToken = RefreshToken::create(
                $userAccount->id(),
                $refreshTokenHash,
                (new DateTimeImmutable())->add(new DateInterval(self::REFRESH_TOKEN_LIFETIME))
            );
            $this->refreshTokens->save($refreshToken);

            return new AuthenticationResult(
                $accessToken->token,
                $refreshTokenValue,
                $accessToken->expiresInSeconds,
                $person->id()->toString(),
                $userAccount->id()->toString(),
                false
            );
        });
    }
}
