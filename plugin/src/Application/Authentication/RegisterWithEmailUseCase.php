<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\OrganizationInvitation\ResolveOrganizationInvitationUseCase;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\UserAccount\EmailAlreadyInUseException;
use StageArt\Domain\Authentication\EmailVerificationToken;
use StageArt\Domain\Authentication\EmailVerificationTokenRepositoryInterface;
use StageArt\Domain\Authentication\RefreshToken;
use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;
use Throwable;

/**
 * The Email+Password mirror of AuthenticateWithGoogleUseCase's new-user
 * branch: creates Person -> UserAccount -> EmailCredential from nothing
 * but an email/password, provisioning the same hidden WordPress User via
 * the same WordPressUserProvisionerInterface Google uses (Person.
 * wordPressUserId's current Infrastructure requirement is provider-
 * agnostic - see this phase's design report). Reuses AuthenticationResult
 * and the exact same Access/Refresh Token issuance as the Google flow,
 * so a client cannot tell which provider a session came from just by
 * inspecting the token shape.
 *
 * UserAccount.md's Password Policy ("最小文字数：8文字以上, 追加の複雑性
 * 要件は設けない") is enforced here with the same MIN_PASSWORD_LENGTH used
 * by RegisterEmailCredentialUseCase.
 *
 * Real-environment finding (confirmed by direct DB inspection, not a
 * hypothetical): this Use Case originally never issued an
 * EmailVerificationToken or called AuthMailerInterface at all - only
 * RequestEmailVerificationUseCase (the resend endpoint) did, so a brand
 * new registration never actually sent a confirmation email despite
 * mobile-rn's registration-pending.tsx immediately telling the user
 * "確認メールを送信しました". TOKEN_LIFETIME/raw-token-generation/hashing
 * below are deliberately inlined the same way RequestEmailVerificationUseCase
 * does it, rather than extracted into a shared helper - this codebase's
 * own established pattern already inlines `bin2hex(random_bytes(32))` +
 * `hash('sha256', ...)` per Use Case for every Token type (RefreshToken
 * in 3 places, PasswordResetToken, EmailVerificationToken), so a new
 * shared abstraction here would be inconsistent with the existing
 * design, not a fix for genuine duplication.
 *
 * StageArt 招待登録のメール確認省略ラウンド (OrganizationInvitation only since
 * R14-B): when `$command->invitationToken` is the token of a usable
 * OrganizationInvitation, that invitation's own `email()` is used for
 * everything below - the duplicate-email check, WordPress user
 * provisioning and the EmailCredential itself, which is marked verified
 * (the link proves the address is reachable) with no confirmation mail -
 * and the invitation is resolved after the registration
 * (ResolveOrganizationInvitationUseCase, failures logged with ids only).
 * The organization invitation handling is unchanged by R14-B (D1).
 *
 * PR #7 R14-B stage 4 (design doc §7.1, §9.2, U5 C案): a Production
 * (participant) invitation is never looked up by token and never resolved
 * here - registering, verifying an address or signing in with Google does
 * not make anyone a participant; the invitee accepts explicitly from
 * `GET /me/participant-invitations`. A token that is not a usable
 * OrganizationInvitation's (an old participant-invitation link, an
 * expired or unknown token) is ignored: the registration is an ordinary
 * one, with exactly the result and response of a registration without a
 * token (the address is not marked verified and the usual confirmation
 * mail is sent) - nothing about any invitation is revealed. Before R14-B
 * such a token failed the registration; the organization invitation's
 * own valid-token path is the same as before.
 */
final class RegisterWithEmailUseCase
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const REFRESH_TOKEN_LIFETIME = 'P30D';
    private const EMAIL_VERIFICATION_TOKEN_LIFETIME = 'PT24H';

    private EmailCredentialRepositoryInterface $emailCredentials;
    private PersonRepositoryInterface $people;
    private UserAccountRepositoryInterface $userAccounts;
    private RefreshTokenRepositoryInterface $refreshTokens;
    private EmailVerificationTokenRepositoryInterface $emailVerificationTokens;
    private AccessTokenIssuerInterface $accessTokenIssuer;
    private WordPressUserProvisionerInterface $wordPressUserProvisioner;
    private TransactionManagerInterface $transactions;
    private AuthMailerInterface $mailer;
    private ResolveOrganizationInvitationUseCase $resolveOrganizationInvitation;
    private OrganizationInvitationRepositoryInterface $organizationInvitations;

    public function __construct(
        EmailCredentialRepositoryInterface $emailCredentials,
        PersonRepositoryInterface $people,
        UserAccountRepositoryInterface $userAccounts,
        RefreshTokenRepositoryInterface $refreshTokens,
        EmailVerificationTokenRepositoryInterface $emailVerificationTokens,
        AccessTokenIssuerInterface $accessTokenIssuer,
        WordPressUserProvisionerInterface $wordPressUserProvisioner,
        TransactionManagerInterface $transactions,
        AuthMailerInterface $mailer,
        ResolveOrganizationInvitationUseCase $resolveOrganizationInvitation,
        OrganizationInvitationRepositoryInterface $organizationInvitations
    ) {
        $this->emailCredentials = $emailCredentials;
        $this->people = $people;
        $this->userAccounts = $userAccounts;
        $this->refreshTokens = $refreshTokens;
        $this->emailVerificationTokens = $emailVerificationTokens;
        $this->accessTokenIssuer = $accessTokenIssuer;
        $this->wordPressUserProvisioner = $wordPressUserProvisioner;
        $this->transactions = $transactions;
        $this->mailer = $mailer;
        $this->resolveOrganizationInvitation = $resolveOrganizationInvitation;
        $this->organizationInvitations = $organizationInvitations;
    }

    public function execute(RegisterWithEmailCommand $command): AuthenticationResult
    {
        if (strlen($command->password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        $organizationInvitation = null;

        if ($command->invitationToken !== null) {
            // R14-B stage 4: only an OrganizationInvitation token is looked up
            // (a participant invitation is never found by token, U5 C案). A
            // token that is not a usable OrganizationInvitation's is ignored
            // - the registration is an ordinary one (see the class docblock).
            $candidate = $this->organizationInvitations->findByTokenHash(hash('sha256', $command->invitationToken));

            if ($candidate !== null && $candidate->isUsable()) {
                $organizationInvitation = $candidate;
            }
        }

        $registrationEmail = $organizationInvitation !== null ? $organizationInvitation->email() : $command->email;
        $hasTrustedInvitation = $organizationInvitation !== null;

        // Captured by reference from inside the transaction closure below
        // so the mailer can be called AFTER the transaction commits, not
        // from within it - sending mail is external I/O, and must not
        // run inside a DB transaction (nor fire at all if the
        // transaction ultimately rolls back). Stays null for the
        // invitation-sourced path, which never creates a verification
        // token at all - see the guarded call below.
        $emailVerificationTokenValue = null;

        $result = $this->transactions->run(function () use ($command, $hasTrustedInvitation, $registrationEmail, &$emailVerificationTokenValue): AuthenticationResult {
            if ($this->emailCredentials->findByEmail($registrationEmail)) {
                throw new EmailAlreadyInUseException('This email address is already registered.');
            }

            $wordPressUserId = $this->wordPressUserProvisioner->provision($registrationEmail);

            $person = Person::create($wordPressUserId);
            $this->people->save($person);

            $userAccount = UserAccount::create($person->id());
            $this->userAccounts->save($userAccount);

            $passwordHash = password_hash($command->password, PASSWORD_DEFAULT);
            $credential = EmailCredential::create($userAccount->id(), $registrationEmail, $passwordHash);

            if ($hasTrustedInvitation) {
                $credential->markEmailVerified();
            }

            $this->emailCredentials->save($credential);

            $accessToken = $this->accessTokenIssuer->issue($userAccount->id(), $person->id());
            $refreshTokenValue = bin2hex(random_bytes(32));
            $refreshTokenHash = hash('sha256', $refreshTokenValue);

            $refreshToken = RefreshToken::create(
                $userAccount->id(),
                $refreshTokenHash,
                (new DateTimeImmutable())->add(new DateInterval(self::REFRESH_TOKEN_LIFETIME))
            );
            $this->refreshTokens->save($refreshToken);

            if (! $hasTrustedInvitation) {
                $emailVerificationTokenValue = bin2hex(random_bytes(32));
                $emailVerificationTokenHash = hash('sha256', $emailVerificationTokenValue);

                $emailVerificationToken = EmailVerificationToken::create(
                    $userAccount->id(),
                    $emailVerificationTokenHash,
                    (new DateTimeImmutable())->add(new DateInterval(self::EMAIL_VERIFICATION_TOKEN_LIFETIME))
                );
                $this->emailVerificationTokens->save($emailVerificationToken);
            }

            return new AuthenticationResult(
                $accessToken->token,
                $refreshTokenValue,
                $accessToken->expiresInSeconds,
                $person->id()->toString(),
                $userAccount->id()->toString(),
                true
            );
        });

        if ($emailVerificationTokenValue !== null) {
            $this->mailer->sendEmailVerificationEmail($registrationEmail, $emailVerificationTokenValue);
        }

        // PR #7 R14-A (F6): organization invitations are resolved here only
        // when the registration came through an organization invitation link
        // - the token proves the registrant received the mail sent to that
        // address. A self-registration's organization invitations are
        // resolved when the address is verified (VerifyEmailUseCase).
        // Participant invitations are never resolved (R14-B stage 4).
        if (! $hasTrustedInvitation) {
            return $result;
        }

        try {
            $this->resolveOrganizationInvitation->execute(PersonId::fromString($result->personId), $registrationEmail);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[StageArt OrganizationInvitation] resolution failed after email registration for person=%s: %s',
                $result->personId,
                $exception->getMessage()
            ));
        }

        return $result;
    }
}
