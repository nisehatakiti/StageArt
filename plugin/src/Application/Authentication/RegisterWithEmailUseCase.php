<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\ResolveParticipantInvitationUseCase;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\UserAccount\EmailAlreadyInUseException;
use StageArt\Domain\Authentication\EmailVerificationToken;
use StageArt\Domain\Authentication\EmailVerificationTokenRepositoryInterface;
use StageArt\Domain\Authentication\RefreshToken;
use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
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
 * StageArt メール招待によるProductionParticipant追加機能: after a
 * successful registration, resolves any outstanding
 * ParticipantInvitation for this email via ResolveParticipantInvitationUseCase
 * - a single delegated call, not inline Participant business logic (see
 * that Use Case's own docblock for how it re-authorizes Participant
 * creation through the invitation's own inviter, never bypassing
 * CreateParticipantUseCase's Authorization check). Wrapped in its own
 * try/catch so an invitation-resolution failure can never turn a
 * successful registration into a failed response - this method's
 * existing return contract (AuthenticationResult) is unchanged.
 *
 * StageArt 招待登録のメール確認省略ラウンド: when `$command->invitationToken`
 * resolves to a usable ParticipantInvitation, that invitation's own
 * `email()` is used for EVERYTHING below - the duplicate-email check,
 * WordPress user provisioning, and the EmailCredential itself - never
 * `$command->email` (the client-submitted value is not trusted for this
 * path; see §7 of this round's instruction: "招待に記録されている
 * メールアドレスを登録メールアドレスとして使用する"). An invalid, expired,
 * cancelled, or already-consumed token fails the whole registration via
 * ParticipantInvitationNotFoundException (reusing the exact exception/
 * "not usable" semantics GetParticipantInvitationByTokenUseCase already
 * established) - it never silently falls back to ordinary registration.
 * The freshly-created EmailCredential is marked verified immediately
 * (the invitation link itself already proves this address is
 * reachable), and no EmailVerificationToken/confirmation mail is ever
 * created for this path. Ordinary self-registration (`invitationToken`
 * null) is completely unchanged.
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
    private ResolveParticipantInvitationUseCase $resolveParticipantInvitation;
    private ParticipantInvitationRepositoryInterface $participantInvitations;

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
        ResolveParticipantInvitationUseCase $resolveParticipantInvitation,
        ParticipantInvitationRepositoryInterface $participantInvitations
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
        $this->resolveParticipantInvitation = $resolveParticipantInvitation;
        $this->participantInvitations = $participantInvitations;
    }

    public function execute(RegisterWithEmailCommand $command): AuthenticationResult
    {
        if (strlen($command->password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        $invitation = null;

        if ($command->invitationToken !== null) {
            $tokenHash = hash('sha256', $command->invitationToken);
            $invitation = $this->participantInvitations->findByTokenHash($tokenHash);

            if ($invitation === null || ! $invitation->isUsable()) {
                throw new ParticipantInvitationNotFoundException('No usable ParticipantInvitation found for this token.');
            }
        }

        $registrationEmail = $invitation !== null ? $invitation->email() : $command->email;

        // Captured by reference from inside the transaction closure below
        // so the mailer can be called AFTER the transaction commits, not
        // from within it - sending mail is external I/O, and must not
        // run inside a DB transaction (nor fire at all if the
        // transaction ultimately rolls back). Stays null for the
        // invitation-sourced path, which never creates a verification
        // token at all - see the guarded call below.
        $emailVerificationTokenValue = null;

        $result = $this->transactions->run(function () use ($command, $invitation, $registrationEmail, &$emailVerificationTokenValue): AuthenticationResult {
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

            if ($invitation !== null) {
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

            if ($invitation === null) {
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

        try {
            $this->resolveParticipantInvitation->execute(PersonId::fromString($result->personId), $registrationEmail);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[StageArt ParticipantInvitation] resolution failed after email registration for person=%s: %s',
                $result->personId,
                $exception->getMessage()
            ));
        }

        return $result;
    }
}
