<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use StageArt\Application\OrganizationInvitation\ResolveOrganizationInvitationUseCase;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Authentication\EmailVerificationTokenRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;
use Throwable;

/**
 * Public (token-based, no login required) by design: the user may click
 * the verification link/enter the code on a different device or session
 * than the one that requested it, matching ResetPasswordUseCase's own
 * public consumption pattern - see UserAccountRestController's docblock
 * for the general "request vs. consume" placement rule this follows.
 *
 * PR #7 R14-A (F6): verifying the address is the moment the owner of the
 * address is known, so it is also the moment the invitations sent to that
 * address are resolved (OrganizationInvitation -> Membership). A
 * self-registration without an invitation token no longer resolves them
 * (RegisterWithEmailUseCase):
 * anyone can register with any address, and before R14-A that let a third
 * party take the invitations sent to someone else's address.
 *
 * Resolution runs after the verification has committed and never undoes
 * it: a failure is logged and the invitation stays PENDING, exactly as on
 * the registration and Google paths. Without the resolvers (wiring that
 * predates R14-A) nothing is resolved here - the safe side.
 *
 * PR #7 R14-B stage 4 (design doc §7.1): Production (participant)
 * invitations are no longer resolved here - verifying an address never
 * makes anyone a participant; the invitee accepts explicitly from
 * `GET /me/participant-invitations`.
 */
final class VerifyEmailUseCase
{
    private EmailVerificationTokenRepositoryInterface $emailVerificationTokens;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private TransactionManagerInterface $transactions;
    private ?UserAccountRepositoryInterface $userAccounts;
    private ?ResolveOrganizationInvitationUseCase $resolveOrganizationInvitation;

    public function __construct(
        EmailVerificationTokenRepositoryInterface $emailVerificationTokens,
        EmailCredentialRepositoryInterface $emailCredentials,
        TransactionManagerInterface $transactions,
        ?UserAccountRepositoryInterface $userAccounts = null,
        ?ResolveOrganizationInvitationUseCase $resolveOrganizationInvitation = null
    ) {
        $this->emailVerificationTokens = $emailVerificationTokens;
        $this->emailCredentials = $emailCredentials;
        $this->transactions = $transactions;
        $this->userAccounts = $userAccounts;
        $this->resolveOrganizationInvitation = $resolveOrganizationInvitation;
    }

    public function execute(VerifyEmailCommand $command): void
    {
        $hash = hash('sha256', $command->token);
        $verificationToken = $this->emailVerificationTokens->findByTokenHash($hash);

        if (! $verificationToken || ! $verificationToken->isUsable()) {
            throw new InvalidEmailVerificationTokenException('This email verification token is invalid, expired, or already used.');
        }

        $verifiedEmail = $this->transactions->run(function () use ($verificationToken): ?string {
            $credential = $this->emailCredentials->findByUserAccountId($verificationToken->userAccountId());

            if ($credential) {
                $credential->markEmailVerified();
                $this->emailCredentials->save($credential);
            }

            $verificationToken->consume();
            $this->emailVerificationTokens->save($verificationToken);

            return $credential ? $credential->email() : null;
        });

        if ($verifiedEmail !== null) {
            $this->resolveInvitations($verificationToken->userAccountId(), $verifiedEmail);
        }
    }

    private function resolveInvitations(UserAccountId $userAccountId, string $email): void
    {
        if ($this->userAccounts === null) {
            return;
        }

        try {
            $userAccount = $this->userAccounts->findById($userAccountId);
        } catch (Throwable $exception) {
            error_log(sprintf('[StageArt Invitation] could not read the account after email verification (account=%s): %s', $userAccountId->toString(), $exception->getMessage()));

            return;
        }

        if ($userAccount === null) {
            return;
        }

        $personId = $userAccount->personId();

        if ($this->resolveOrganizationInvitation !== null) {
            try {
                $this->resolveOrganizationInvitation->execute($personId, $email);
            } catch (Throwable $exception) {
                error_log(sprintf('[StageArt OrganizationInvitation] resolution failed after email verification for person=%s: %s', $personId->toString(), $exception->getMessage()));
            }
        }
    }
}
