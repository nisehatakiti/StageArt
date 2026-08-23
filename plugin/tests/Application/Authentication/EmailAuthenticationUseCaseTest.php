<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Authentication;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Authentication\AuthenticateWithEmailCommand;
use StageArt\Application\Authentication\AuthenticateWithEmailUseCase;
use StageArt\Application\Authentication\InvalidCredentialsException;
use StageArt\Application\Authentication\InvalidEmailVerificationTokenException;
use StageArt\Application\Authentication\InvalidPasswordResetTokenException;
use StageArt\Application\Authentication\RegisterWithEmailCommand;
use StageArt\Application\Authentication\RegisterWithEmailUseCase;
use StageArt\Application\Authentication\RequestPasswordResetCommand;
use StageArt\Application\Authentication\RequestPasswordResetUseCase;
use StageArt\Application\Authentication\ResetPasswordCommand;
use StageArt\Application\Authentication\ResetPasswordUseCase;
use StageArt\Application\Authentication\VerifyEmailCommand;
use StageArt\Application\Authentication\VerifyEmailUseCase;
use StageArt\Application\UserAccount\EmailAlreadyInUseException;
use StageArt\Tests\Support\FakeAccessTokenIssuer;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\FakeWordPressUserProvisioner;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryEmailVerificationTokenRepository;
use StageArt\Tests\Support\InMemoryPasswordResetTokenRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryRefreshTokenRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class EmailAuthenticationUseCaseTest extends TestCase
{
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryRefreshTokenRepository $refreshTokens;
    private InMemoryPasswordResetTokenRepository $passwordResetTokens;
    private InMemoryEmailVerificationTokenRepository $emailVerificationTokens;
    private FakeAccessTokenIssuer $accessTokenIssuer;
    private FakeWordPressUserProvisioner $wordPressUserProvisioner;
    private FakeAuthMailer $mailer;

    private RegisterWithEmailUseCase $registerWithEmail;
    private AuthenticateWithEmailUseCase $authenticateWithEmail;
    private RequestPasswordResetUseCase $requestPasswordReset;
    private ResetPasswordUseCase $resetPassword;
    private VerifyEmailUseCase $verifyEmail;

    protected function setUp(): void
    {
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->refreshTokens = new InMemoryRefreshTokenRepository();
        $this->passwordResetTokens = new InMemoryPasswordResetTokenRepository();
        $this->emailVerificationTokens = new InMemoryEmailVerificationTokenRepository();
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
        $this->requestPasswordReset = new RequestPasswordResetUseCase(
            $this->emailCredentials,
            $this->passwordResetTokens,
            $this->mailer,
            new InMemoryTransactionManager()
        );
        $this->resetPassword = new ResetPasswordUseCase(
            $this->passwordResetTokens,
            $this->emailCredentials,
            $this->refreshTokens,
            new InMemoryTransactionManager()
        );
        $this->verifyEmail = new VerifyEmailUseCase(
            $this->emailVerificationTokens,
            $this->emailCredentials,
            new InMemoryTransactionManager()
        );
    }

    // --- RegisterWithEmailUseCase ---------------------------------------

    public function test_new_email_registration_creates_person_useraccount_and_credential(): void
    {
        $result = $this->registerWithEmail->execute(new RegisterWithEmailCommand('alice@example.com', 'password123'));

        $this->assertTrue($result->isNewUser);
        $this->assertNotEmpty($result->accessToken);
        $this->assertNotEmpty($result->refreshToken);

        $credential = $this->emailCredentials->findByEmail('alice@example.com');
        $this->assertNotNull($credential);
        $this->assertSame($result->userAccountId, $credential->userAccountId()->toString());
        $this->assertSame(100, $this->people->findById(
            $this->userAccounts->findById($credential->userAccountId())->personId()
        )->wordPressUserId());
    }

    /**
     * The real-environment bug this test guards against: registration
     * previously created the UserAccount/EmailCredential but never
     * issued an EmailVerificationToken or called the mailer at all -
     * only the resend endpoint (RequestEmailVerificationUseCase) did,
     * so a brand-new registration never actually sent a confirmation
     * email despite the client immediately showing "確認メールを送信
     * しました". Confirmed by direct production-database inspection
     * before this fix, not a hypothetical.
     */
    public function test_new_email_registration_issues_a_verification_token_and_sends_the_email(): void
    {
        $result = $this->registerWithEmail->execute(new RegisterWithEmailCommand('erin@example.com', 'password123'));

        $this->assertCount(1, $this->mailer->verificationEmails);
        $this->assertSame('erin@example.com', $this->mailer->verificationEmails[0]['to']);
        $sentToken = $this->mailer->verificationEmails[0]['token'];
        $this->assertNotEmpty($sentToken);

        // The token handed to the mailer must be the same one that was
        // actually persisted (hashed) - not merely "a" token that
        // happens to also exist.
        $userAccount = $this->userAccounts->findByPersonId(
            $this->people->findByWordPressUserId(100)->id()
        );
        $this->assertNotNull($userAccount);
        $stored = $this->emailVerificationTokens->findByTokenHash(hash('sha256', $sentToken));
        $this->assertNotNull($stored);
        $this->assertSame($userAccount->id()->toString(), $stored->userAccountId()->toString());

        // The Backend Phase 2 API response contract is unchanged by
        // this fix - still exactly AuthenticationResult, nothing added.
        $this->assertTrue($result->isNewUser);
        $this->assertNotEmpty($result->accessToken);
        $this->assertNotEmpty($result->refreshToken);
    }

    public function test_registering_a_duplicate_email_is_rejected(): void
    {
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('bob@example.com', 'password123'));

        $this->expectException(EmailAlreadyInUseException::class);
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('bob@example.com', 'different456'));
    }

    public function test_registering_with_a_too_short_password_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('carol@example.com', 'short'));
    }

    // --- AuthenticateWithEmailUseCase -----------------------------------

    public function test_login_with_correct_credentials_succeeds(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('dave@example.com', 'password123'));

        $result = $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('dave@example.com', 'password123'));

        $this->assertFalse($result->isNewUser);
        $this->assertSame($registered->personId, $result->personId);
        $this->assertSame($registered->userAccountId, $result->userAccountId);
        $this->assertNotSame($registered->accessToken, $result->accessToken);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('erin@example.com', 'password123'));

        $this->expectException(InvalidCredentialsException::class);
        $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('erin@example.com', 'wrong-password'));
    }

    public function test_login_with_unknown_email_is_rejected(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('nobody@example.com', 'password123'));
    }

    // --- RequestPasswordResetUseCase / ResetPasswordUseCase -------------

    public function test_password_reset_flow_changes_the_password_and_revokes_existing_sessions(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('frank@example.com', 'oldpassword1'));

        $this->requestPasswordReset->execute(new RequestPasswordResetCommand('frank@example.com'));
        $this->assertCount(1, $this->mailer->passwordResetEmails);
        $token = $this->mailer->passwordResetEmails[0]['token'];

        $this->resetPassword->execute(new ResetPasswordCommand($token, 'newpassword2'));

        // Old password no longer works, new one does.
        $loginFailed = false;
        try {
            $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('frank@example.com', 'oldpassword1'));
        } catch (InvalidCredentialsException $exception) {
            $loginFailed = true;
        }
        $this->assertTrue($loginFailed);

        $loginResult = $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('frank@example.com', 'newpassword2'));
        $this->assertSame($registered->userAccountId, $loginResult->userAccountId);

        // The Refresh Token issued at registration must have been revoked by the reset.
        $originalHash = hash('sha256', $registered->refreshToken);
        $this->assertTrue($this->refreshTokens->findByTokenHash($originalHash)->isRevoked());
    }

    public function test_requesting_a_reset_for_an_unknown_email_is_a_silent_no_op(): void
    {
        $this->requestPasswordReset->execute(new RequestPasswordResetCommand('unknown@example.com'));

        $this->assertCount(0, $this->mailer->passwordResetEmails);
    }

    public function test_resetting_with_an_unknown_token_is_rejected(): void
    {
        $this->expectException(InvalidPasswordResetTokenException::class);
        $this->resetPassword->execute(new ResetPasswordCommand('never-issued', 'newpassword2'));
    }

    public function test_resetting_with_an_already_used_token_is_rejected(): void
    {
        $this->registerWithEmail->execute(new RegisterWithEmailCommand('grace@example.com', 'oldpassword1'));
        $this->requestPasswordReset->execute(new RequestPasswordResetCommand('grace@example.com'));
        $token = $this->mailer->passwordResetEmails[0]['token'];

        $this->resetPassword->execute(new ResetPasswordCommand($token, 'newpassword2'));

        $this->expectException(InvalidPasswordResetTokenException::class);
        $this->resetPassword->execute(new ResetPasswordCommand($token, 'thirdpassword3'));
    }

    // --- VerifyEmailUseCase ----------------------------------------------

    public function test_verifying_with_an_unknown_token_is_rejected(): void
    {
        $this->expectException(InvalidEmailVerificationTokenException::class);
        $this->verifyEmail->execute(new VerifyEmailCommand('never-issued'));
    }
}
