<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Authentication;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Authentication\AuthenticateWithGoogleCommand;
use StageArt\Application\Authentication\AuthenticateWithGoogleUseCase;
use StageArt\Application\Authentication\ExternalIdentityAlreadyLinkedException;
use StageArt\Application\Authentication\InvalidGoogleIdTokenException;
use StageArt\Application\Authentication\InvalidRefreshTokenException;
use StageArt\Application\Authentication\LinkGoogleIdentityCommand;
use StageArt\Application\Authentication\LinkGoogleIdentityUseCase;
use StageArt\Application\Authentication\LogoutCommand;
use StageArt\Application\Authentication\LogoutUseCase;
use StageArt\Application\Authentication\RefreshAccessTokenCommand;
use StageArt\Application\Authentication\RefreshAccessTokenUseCase;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\ExternalIdentity;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountId;
use StageArt\Tests\Support\FakeAccessTokenIssuer;
use StageArt\Tests\Support\FakeGoogleIdTokenVerifier;
use StageArt\Tests\Support\FakeWordPressUserProvisioner;
use StageArt\Tests\Support\InMemoryExternalIdentityRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryRefreshTokenRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class AuthenticationUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryExternalIdentityRepository $externalIdentities;
    private InMemoryRefreshTokenRepository $refreshTokens;
    private FakeGoogleIdTokenVerifier $googleVerifier;
    private FakeAccessTokenIssuer $accessTokenIssuer;
    private FakeWordPressUserProvisioner $wordPressUserProvisioner;

    private AuthenticateWithGoogleUseCase $authenticateWithGoogle;
    private RefreshAccessTokenUseCase $refreshAccessToken;
    private LogoutUseCase $logout;
    private LinkGoogleIdentityUseCase $linkGoogleIdentity;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->externalIdentities = new InMemoryExternalIdentityRepository();
        $this->refreshTokens = new InMemoryRefreshTokenRepository();
        $this->googleVerifier = new FakeGoogleIdTokenVerifier();
        $this->accessTokenIssuer = new FakeAccessTokenIssuer();
        $this->wordPressUserProvisioner = new FakeWordPressUserProvisioner();
        $transactions = new InMemoryTransactionManager();

        $this->authenticateWithGoogle = new AuthenticateWithGoogleUseCase(
            $this->googleVerifier,
            $this->externalIdentities,
            $this->userAccounts,
            $this->people,
            $this->refreshTokens,
            $this->accessTokenIssuer,
            $this->wordPressUserProvisioner,
            $transactions
        );
        $this->refreshAccessToken = new RefreshAccessTokenUseCase(
            $this->refreshTokens,
            $this->userAccounts,
            $this->people,
            $this->accessTokenIssuer
        );
        $this->logout = new LogoutUseCase($this->refreshTokens);
        $this->linkGoogleIdentity = new LinkGoogleIdentityUseCase(
            $this->googleVerifier,
            $this->people,
            $this->userAccounts,
            $this->externalIdentities,
            new InMemoryTransactionManager()
        );
    }

    // --- AuthenticateWithGoogleUseCase ---------------------------------

    public function test_new_google_user_creates_person_useraccount_externalidentity_and_provisions_wordpress_user(): void
    {
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-1', 'someone@example.com');

        $result = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('valid-token'));

        $this->assertTrue($result->isNewUser);
        $this->assertNotEmpty($result->accessToken);
        $this->assertNotEmpty($result->refreshToken);

        $identity = $this->externalIdentities->findByProviderAndProviderUserId('google', 'google-sub-1');
        $this->assertNotNull($identity);

        $userAccount = $this->userAccounts->findById($identity->userAccountId());
        $this->assertNotNull($userAccount);
        $this->assertSame($result->userAccountId, $userAccount->id()->toString());

        $person = $this->people->findById($userAccount->personId());
        $this->assertNotNull($person);
        $this->assertSame($result->personId, $person->id()->toString());
        // A hidden WordPress User was auto-provisioned - never exposed
        // in the Result itself (no wordPressUserId field on it at all).
        $this->assertSame(100, $person->wordPressUserId());
    }

    public function test_existing_google_user_resolves_the_same_person_without_creating_duplicates(): void
    {
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-1', 'someone@example.com');

        $first = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('valid-token'));
        $second = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('valid-token'));

        $this->assertFalse($second->isNewUser);
        $this->assertSame($first->personId, $second->personId);
        $this->assertSame($first->userAccountId, $second->userAccountId);
        // Two separate Access/Refresh Token pairs, one per login.
        $this->assertNotSame($first->accessToken, $second->accessToken);
        $this->assertNotSame($first->refreshToken, $second->refreshToken);

        $userAccountId = UserAccountId::fromString($first->userAccountId);
        $this->assertCount(1, $this->externalIdentities->findByUserAccountId($userAccountId));

        // The fake starts issuing WordPress User IDs at 100; if the second
        // (existing-identity) authenticate() call had wrongly provisioned
        // another one, this next fresh call would yield 102, not 101.
        $nextProvisionedId = $this->wordPressUserProvisioner->provision(null);
        $this->assertSame(101, $nextProvisionedId);
    }

    public function test_invalid_google_id_token_throws_and_creates_nothing(): void
    {
        $this->expectException(InvalidGoogleIdTokenException::class);

        try {
            $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('not-a-real-token'));
        } finally {
            $this->assertNull($this->externalIdentities->findByProviderAndProviderUserId('google', 'google-sub-1'));
        }
    }

    // --- RefreshAccessTokenUseCase -------------------------------------

    public function test_valid_refresh_token_issues_a_new_access_token(): void
    {
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-2', null);
        $auth = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('valid-token'));

        $result = $this->refreshAccessToken->execute(new RefreshAccessTokenCommand($auth->refreshToken));

        $this->assertNotEmpty($result->accessToken);
        $this->assertNotSame($auth->accessToken, $result->accessToken);
    }

    public function test_unknown_refresh_token_is_rejected(): void
    {
        $this->expectException(InvalidRefreshTokenException::class);

        $this->refreshAccessToken->execute(new RefreshAccessTokenCommand('never-issued'));
    }

    public function test_revoked_refresh_token_is_rejected(): void
    {
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-3', null);
        $auth = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('valid-token'));

        $this->logout->execute(new LogoutCommand($auth->refreshToken));

        $this->expectException(InvalidRefreshTokenException::class);
        $this->refreshAccessToken->execute(new RefreshAccessTokenCommand($auth->refreshToken));
    }

    // --- LogoutUseCase ---------------------------------------------------

    public function test_logout_revokes_the_refresh_token(): void
    {
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-4', null);
        $auth = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('valid-token'));

        $this->logout->execute(new LogoutCommand($auth->refreshToken));

        $hash = hash('sha256', $auth->refreshToken);
        $this->assertTrue($this->refreshTokens->findByTokenHash($hash)->isRevoked());
    }

    public function test_logout_with_an_unknown_refresh_token_is_a_silent_no_op(): void
    {
        $this->logout->execute(new LogoutCommand('never-issued'));
        $this->addToAssertionCount(1); // Reaching here without an exception is the assertion.
    }

    public function test_logout_is_idempotent(): void
    {
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-5', null);
        $auth = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('valid-token'));

        $this->logout->execute(new LogoutCommand($auth->refreshToken));
        $this->logout->execute(new LogoutCommand($auth->refreshToken));

        $this->addToAssertionCount(1);
    }

    // --- LinkGoogleIdentityUseCase --------------------------------------

    public function test_existing_wordpress_user_without_useraccount_gets_one_created_and_linked(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-6', null);

        $result = $this->linkGoogleIdentity->execute(new LinkGoogleIdentityCommand(1, 'valid-token'));

        $userAccount = $this->userAccounts->findByPersonId($person->id());
        $this->assertNotNull($userAccount);
        $this->assertSame($result->id, $userAccount->id()->toString());

        $identity = $this->externalIdentities->findByProviderAndProviderUserId('google', 'google-sub-6');
        $this->assertNotNull($identity);
        $this->assertTrue($identity->userAccountId()->equals($userAccount->id()));
    }

    public function test_existing_wordpress_user_with_useraccount_links_to_the_same_account(): void
    {
        $person = Person::create(2);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-7', null);

        $this->linkGoogleIdentity->execute(new LinkGoogleIdentityCommand(2, 'valid-token'));

        // Still exactly one UserAccount for this Person - not a second one.
        $this->assertSame(
            $userAccount->id()->toString(),
            $this->userAccounts->findByPersonId($person->id())->id()->toString()
        );
    }

    public function test_linking_an_identity_already_linked_elsewhere_is_rejected(): void
    {
        $ownerPerson = Person::create(3);
        $this->people->save($ownerPerson);
        $ownerAccount = UserAccount::create($ownerPerson->id());
        $this->userAccounts->save($ownerAccount);
        $this->externalIdentities->save(ExternalIdentity::create($ownerAccount->id(), 'google', 'google-sub-8'));

        $otherPerson = Person::create(4);
        $this->people->save($otherPerson);
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-8', null);

        $this->expectException(ExternalIdentityAlreadyLinkedException::class);
        $this->linkGoogleIdentity->execute(new LinkGoogleIdentityCommand(4, 'valid-token'));
    }

    public function test_linking_the_same_identity_twice_to_ones_own_account_is_idempotent(): void
    {
        $person = Person::create(5);
        $this->people->save($person);
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-9', null);

        $this->linkGoogleIdentity->execute(new LinkGoogleIdentityCommand(5, 'valid-token'));
        $this->linkGoogleIdentity->execute(new LinkGoogleIdentityCommand(5, 'valid-token'));

        $userAccount = $this->userAccounts->findByPersonId($person->id());
        $this->assertCount(1, $this->externalIdentities->findByUserAccountId($userAccount->id()));
    }
}
