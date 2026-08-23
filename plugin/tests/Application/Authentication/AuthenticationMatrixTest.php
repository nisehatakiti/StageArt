<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Authentication;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Authentication\AuthenticateWithEmailCommand;
use StageArt\Application\Authentication\AuthenticateWithEmailUseCase;
use StageArt\Application\Authentication\AuthenticateWithGoogleCommand;
use StageArt\Application\Authentication\AuthenticateWithGoogleUseCase;
use StageArt\Application\Authentication\InvalidCredentialsException;
use StageArt\Application\Authentication\InvalidRefreshTokenException;
use StageArt\Application\Authentication\LinkGoogleIdentityCommand;
use StageArt\Application\Authentication\LinkGoogleIdentityUseCase;
use StageArt\Application\Authentication\LogoutCommand;
use StageArt\Application\Authentication\LogoutUseCase;
use StageArt\Application\Authentication\RefreshAccessTokenCommand;
use StageArt\Application\Authentication\RefreshAccessTokenUseCase;
use StageArt\Application\Authentication\RegisterWithEmailCommand;
use StageArt\Application\Authentication\RegisterWithEmailUseCase;
use StageArt\Application\UserAccount\EmailAlreadyInUseException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\UserAccountId;
use StageArt\Tests\Support\FakeAccessTokenIssuer;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\FakeGoogleIdTokenVerifier;
use StageArt\Tests\Support\FakeWordPressUserProvisioner;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryEmailVerificationTokenRepository;
use StageArt\Tests\Support\InMemoryExternalIdentityRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryRefreshTokenRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * The explicit Google x Email+Password authentication matrix requested
 * alongside this Phase's approval (12 minimum cases). Each test method
 * name below maps 1:1 to one of the 12 numbered cases in that request,
 * so the mapping stays traceable from the test list alone.
 */
final class AuthenticationMatrixTest extends TestCase
{
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryExternalIdentityRepository $externalIdentities;
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryMembershipRepository $memberships;
    private InMemoryRefreshTokenRepository $refreshTokens;
    private InMemoryEmailVerificationTokenRepository $emailVerificationTokens;
    private FakeGoogleIdTokenVerifier $googleVerifier;
    private FakeAccessTokenIssuer $accessTokenIssuer;
    private FakeWordPressUserProvisioner $wordPressUserProvisioner;
    private FakeAuthMailer $mailer;

    private RegisterWithEmailUseCase $registerWithEmail;
    private AuthenticateWithEmailUseCase $authenticateWithEmail;
    private AuthenticateWithGoogleUseCase $authenticateWithGoogle;
    private LinkGoogleIdentityUseCase $linkGoogleIdentity;
    private RefreshAccessTokenUseCase $refreshAccessToken;
    private LogoutUseCase $logout;

    protected function setUp(): void
    {
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->externalIdentities = new InMemoryExternalIdentityRepository();
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->refreshTokens = new InMemoryRefreshTokenRepository();
        $this->emailVerificationTokens = new InMemoryEmailVerificationTokenRepository();
        $this->googleVerifier = new FakeGoogleIdTokenVerifier();
        $this->accessTokenIssuer = new FakeAccessTokenIssuer();
        $this->wordPressUserProvisioner = new FakeWordPressUserProvisioner();
        $this->mailer = new FakeAuthMailer();

        $this->registerWithEmail = new RegisterWithEmailUseCase(
            $this->emailCredentials,
            $this->people,
            $this->userAccounts,
            $this->refreshTokens,
            $this->emailVerificationTokens,
            $this->accessTokenIssuer,
            $this->wordPressUserProvisioner,
            new InMemoryTransactionManager(),
            $this->mailer
        );
        $this->authenticateWithEmail = new AuthenticateWithEmailUseCase(
            $this->emailCredentials,
            $this->userAccounts,
            $this->people,
            $this->refreshTokens,
            $this->accessTokenIssuer,
            new InMemoryTransactionManager()
        );
        $this->authenticateWithGoogle = new AuthenticateWithGoogleUseCase(
            $this->googleVerifier,
            $this->externalIdentities,
            $this->userAccounts,
            $this->people,
            $this->refreshTokens,
            $this->accessTokenIssuer,
            $this->wordPressUserProvisioner,
            new InMemoryTransactionManager()
        );
        $this->linkGoogleIdentity = new LinkGoogleIdentityUseCase(
            $this->googleVerifier,
            $this->people,
            $this->userAccounts,
            $this->externalIdentities,
            new InMemoryTransactionManager()
        );
        $this->refreshAccessToken = new RefreshAccessTokenUseCase(
            $this->refreshTokens,
            $this->userAccounts,
            $this->people,
            $this->accessTokenIssuer
        );
        $this->logout = new LogoutUseCase($this->refreshTokens);
    }

    // 1. Email新規登録
    public function test_case_1_email_new_registration(): void
    {
        $result = $this->registerWithEmail->execute(new RegisterWithEmailCommand('m1@example.com', 'password123'));

        $this->assertTrue($result->isNewUser);
        $this->assertNotNull($this->emailCredentials->findByEmail('m1@example.com'));
    }

    // 2. Emailログイン
    public function test_case_2_email_login(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('m2@example.com', 'password123'));

        $result = $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('m2@example.com', 'password123'));

        $this->assertSame($registered->userAccountId, $result->userAccountId);
        $this->assertFalse($result->isNewUser);
    }

    // 3. 不正パスワード
    public function test_case_3_wrong_password(): void
    {
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('m3@example.com', 'password123'));

        $this->expectException(InvalidCredentialsException::class);
        $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('m3@example.com', 'wrong-password'));
    }

    // 4. 重複メール登録
    public function test_case_4_duplicate_email_registration(): void
    {
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('m4@example.com', 'password123'));

        $this->expectException(EmailAlreadyInUseException::class);
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('m4@example.com', 'different456'));
    }

    // 5. Google新規登録
    public function test_case_5_google_new_registration(): void
    {
        $this->googleVerifier->registerValidToken('token-m5', 'google-sub-m5', 'm5@example.com');

        $result = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('token-m5'));

        $this->assertTrue($result->isNewUser);
        $this->assertNotNull($this->externalIdentities->findByProviderAndProviderUserId('google', 'google-sub-m5'));
    }

    /**
     * StageArt Authentication Phase 6: family_name/given_name are UI
     * hints only, threaded straight from GoogleIdTokenClaims into
     * AuthenticationResult - never written to Person automatically (no
     * Person is even inspected here, only the returned hint values).
     */
    public function test_google_token_with_name_claims_surfaces_them_as_hints(): void
    {
        $this->googleVerifier->registerValidToken('token-m5-name', 'google-sub-m5-name', 'named@example.com', '秦', '良輔');

        $result = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('token-m5-name'));

        $this->assertSame('秦', $result->familyNameHint);
        $this->assertSame('良輔', $result->givenNameHint);
    }

    /**
     * Google does not guarantee family_name/given_name are present (see
     * GoogleIdTokenClaims's own docblock) - this must not throw or
     * substitute a fabricated value, only surface null so mobile-rn's
     * set-name.tsx falls back to an empty form.
     */
    public function test_google_token_without_name_claims_surfaces_null_hints(): void
    {
        $this->googleVerifier->registerValidToken('token-m5-noname', 'google-sub-m5-noname', 'noname@example.com');

        $result = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('token-m5-noname'));

        $this->assertNull($result->familyNameHint);
        $this->assertNull($result->givenNameHint);
    }

    // 6. Google既存ログイン
    public function test_case_6_google_existing_login(): void
    {
        $this->googleVerifier->registerValidToken('token-m6', 'google-sub-m6', null);
        $first = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('token-m6'));

        $second = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('token-m6'));

        $this->assertFalse($second->isNewUser);
        $this->assertSame($first->userAccountId, $second->userAccountId);
    }

    // 7. 同一UserAccountへのGoogle連携
    public function test_case_7_linking_google_to_the_same_useraccount(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('m7@example.com', 'password123'));
        $wordPressUserId = $this->people->findById(PersonId::fromString($registered->personId))->wordPressUserId();

        $this->googleVerifier->registerValidToken('token-m7', 'google-sub-m7', null);
        $this->linkGoogleIdentity->execute(new LinkGoogleIdentityCommand($wordPressUserId, 'token-m7'));

        $googleLogin = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('token-m7'));

        // Same UserAccount now reachable via either provider - not a new one.
        $this->assertFalse($googleLogin->isNewUser);
        $this->assertSame($registered->userAccountId, $googleLogin->userAccountId);
        $this->assertSame($registered->personId, $googleLogin->personId);

        $userAccountId = UserAccountId::fromString($registered->userAccountId);
        $this->assertNotNull($this->emailCredentials->findByUserAccountId($userAccountId));
        $this->assertCount(1, $this->externalIdentities->findByUserAccountId($userAccountId));
    }

    // 8. GoogleとEmailの別UserAccount誤統合防止
    public function test_case_8_google_and_email_do_not_auto_merge_on_matching_email(): void
    {
        $emailRegistered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('m8@example.com', 'password123'));

        // A Google account whose own claimed email happens to match - but was
        // never explicitly linked - must never be treated as the same account.
        $this->googleVerifier->registerValidToken('token-m8', 'google-sub-m8', 'm8@example.com');
        $googleRegistered = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('token-m8'));

        $this->assertTrue($googleRegistered->isNewUser);
        $this->assertNotSame($emailRegistered->userAccountId, $googleRegistered->userAccountId);
        $this->assertNotSame($emailRegistered->personId, $googleRegistered->personId);
    }

    // 9. Access Token発行
    public function test_case_9_access_token_is_issued_for_both_providers(): void
    {
        $emailResult = $this->registerWithEmail->execute(new RegisterWithEmailCommand('m9a@example.com', 'password123'));

        $this->googleVerifier->registerValidToken('token-m9', 'google-sub-m9', null);
        $googleResult = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('token-m9'));

        $this->assertNotEmpty($emailResult->accessToken);
        $this->assertNotEmpty($googleResult->accessToken);
        $this->assertGreaterThan(0, $emailResult->expiresIn);
        $this->assertGreaterThan(0, $googleResult->expiresIn);
    }

    // 10. Refresh Token
    public function test_case_10_refresh_token_issued_via_email_login_is_exchangeable(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('m10@example.com', 'password123'));

        $refreshed = $this->refreshAccessToken->execute(new RefreshAccessTokenCommand($registered->refreshToken));

        $this->assertNotEmpty($refreshed->accessToken);
        $this->assertNotSame($registered->accessToken, $refreshed->accessToken);
    }

    // 11. Logout
    public function test_case_11_logout_revokes_a_refresh_token_issued_via_email_login(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('m11@example.com', 'password123'));

        $this->logout->execute(new LogoutCommand($registered->refreshToken));

        $this->expectException(InvalidRefreshTokenException::class);
        $this->refreshAccessToken->execute(new RefreshAccessTokenCommand($registered->refreshToken));
    }

    // 12. Organization未所属ユーザーのログイン
    public function test_case_12_organization_unaffiliated_user_can_register_and_login(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('m12@example.com', 'password123'));
        $loggedIn = $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('m12@example.com', 'password123'));

        $personId = PersonId::fromString($registered->personId);
        $this->assertCount(0, $this->memberships->findByPersonId($personId));

        // Neither registration nor login threw despite zero Organization affiliation.
        $this->assertSame($registered->personId, $loggedIn->personId);
    }
}
