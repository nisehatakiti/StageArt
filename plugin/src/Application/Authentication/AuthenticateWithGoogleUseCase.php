<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Authentication\RefreshToken;
use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\ExternalIdentity;
use StageArt\Domain\UserAccount\ExternalIdentityRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * The Phase 2 authentication flow: Google ID Token → ExternalIdentity →
 * UserAccount → Person, creating whichever of these do not exist yet
 * (see this Phase's design report §1/§5). Reuses the existing
 * UserAccount/Person/ExternalIdentity Domain Entities unchanged - no new
 * Identity concept is introduced (RefreshToken, used below, is a
 * Session/Token artifact, not an Identity - see RefreshToken's own
 * docblock).
 *
 * The Google ID Token itself is verified once, at the top, and then
 * discarded - it is never stored, never reused as an API credential
 * (this Phase's explicit requirement), and never used as an identity
 * lookup key beyond its `sub` claim (UserAccount.md: "emailを...主キー
 * にしない").
 */
final class AuthenticateWithGoogleUseCase
{
    private const REFRESH_TOKEN_LIFETIME = 'P30D';

    private GoogleIdTokenVerifierInterface $googleVerifier;
    private ExternalIdentityRepositoryInterface $externalIdentities;
    private UserAccountRepositoryInterface $userAccounts;
    private PersonRepositoryInterface $people;
    private RefreshTokenRepositoryInterface $refreshTokens;
    private AccessTokenIssuerInterface $accessTokenIssuer;
    private WordPressUserProvisionerInterface $wordPressUserProvisioner;
    private TransactionManagerInterface $transactions;

    public function __construct(
        GoogleIdTokenVerifierInterface $googleVerifier,
        ExternalIdentityRepositoryInterface $externalIdentities,
        UserAccountRepositoryInterface $userAccounts,
        PersonRepositoryInterface $people,
        RefreshTokenRepositoryInterface $refreshTokens,
        AccessTokenIssuerInterface $accessTokenIssuer,
        WordPressUserProvisionerInterface $wordPressUserProvisioner,
        TransactionManagerInterface $transactions
    ) {
        $this->googleVerifier = $googleVerifier;
        $this->externalIdentities = $externalIdentities;
        $this->userAccounts = $userAccounts;
        $this->people = $people;
        $this->refreshTokens = $refreshTokens;
        $this->accessTokenIssuer = $accessTokenIssuer;
        $this->wordPressUserProvisioner = $wordPressUserProvisioner;
        $this->transactions = $transactions;
    }

    public function execute(AuthenticateWithGoogleCommand $command): AuthenticationResult
    {
        $claims = $this->googleVerifier->verify($command->idToken);

        return $this->transactions->run(function () use ($claims): AuthenticationResult {
            $existingIdentity = $this->externalIdentities->findByProviderAndProviderUserId('google', $claims->sub);
            $isNewUser = $existingIdentity === null;

            if ($existingIdentity) {
                $userAccount = $this->userAccounts->findById($existingIdentity->userAccountId());
                $person = $this->people->findById($userAccount->personId());
            } else {
                $wordPressUserId = $this->wordPressUserProvisioner->provision($claims->email);

                $person = Person::create($wordPressUserId);
                $this->people->save($person);

                $userAccount = UserAccount::create($person->id());
                $this->userAccounts->save($userAccount);

                $identity = ExternalIdentity::create($userAccount->id(), 'google', $claims->sub);
                $this->externalIdentities->save($identity);
            }

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
                $isNewUser,
                $claims->familyName,
                $claims->givenName
            );
        });
    }
}
