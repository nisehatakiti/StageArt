<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Application\Person\AmbiguousPersonEmailException;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\InvitationEmailAddress;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * R14-B §4.3 / §6.4 / §7.4: decides whether an invitation is addressed to
 * the logged-in Person - the one recipient check of the invitation screens
 * opened from the mail (GetMyParticipantInvitationUseCase,
 * GetMyOrganizationInvitationUseCase; invitation policy 2026-11-27 - the
 * acceptance, the rejection and the invitee's list were removed).
 *
 * recipient():
 * - the WordPress user must resolve to a StageArt Person whose UserAccount
 *   is ACTIVE - checked on every call, whatever the access token says
 *   (SUSPENDED / DISABLED are refused for accept and reject alike);
 * - the Person's verified addresses are collected: the login
 *   EmailCredential only when its address is verified, and the
 *   NotificationEmail only when verified. An unverified address is never
 *   used (F6).
 *
 * isAddressedTo(): the invitation's address must equal one of those
 * verified addresses under InvitationEmailAddress::normalize() (PHP exact
 * comparison - the database collation never decides), AND
 * FindPersonByEmailUseCase must resolve that address to this same Person.
 * An address that resolves to more than one Person (ambiguous) or to
 * someone else is never a match.
 *
 * Callers answer an invitation that is not addressed to the Person with
 * the same 404 as a missing one, so nothing about it is revealed.
 */
final class InvitationRecipientVerifier
{
    private ProductionAuthorizationService $authorization;
    private UserAccountRepositoryInterface $userAccounts;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private NotificationEmailRepositoryInterface $notificationEmails;
    private FindPersonByEmailUseCase $findPersonByEmail;

    public function __construct(
        ProductionAuthorizationService $authorization,
        UserAccountRepositoryInterface $userAccounts,
        EmailCredentialRepositoryInterface $emailCredentials,
        NotificationEmailRepositoryInterface $notificationEmails,
        FindPersonByEmailUseCase $findPersonByEmail
    ) {
        $this->authorization = $authorization;
        $this->userAccounts = $userAccounts;
        $this->emailCredentials = $emailCredentials;
        $this->notificationEmails = $notificationEmails;
        $this->findPersonByEmail = $findPersonByEmail;
    }

    /**
     * @throws InvitationRecipientNotEligibleException no Person, or a
     *   UserAccount that is missing or not ACTIVE
     */
    public function recipient(int $wordPressUserId): VerifiedInvitationRecipient
    {
        $person = $this->authorization->resolveCurrentPerson($wordPressUserId);

        if ($person === null) {
            throw new InvitationRecipientNotEligibleException('No StageArt Person is linked to this user.');
        }

        $account = $this->userAccounts->findByPersonId($person->id());

        if ($account === null || ! $account->isActive()) {
            throw new InvitationRecipientNotEligibleException('The account cannot answer invitations.');
        }

        $addresses = [];
        $credential = $this->emailCredentials->findByUserAccountId($account->id());

        if ($credential !== null && $credential->isEmailVerified()) {
            $addresses[] = InvitationEmailAddress::normalize($credential->email());
        }

        $notificationEmail = $this->notificationEmails->findByPersonId($person->id());

        if ($notificationEmail !== null && $notificationEmail->verified()) {
            $addresses[] = InvitationEmailAddress::normalize($notificationEmail->email());
        }

        return new VerifiedInvitationRecipient($person, array_values(array_filter($addresses, static fn (string $a): bool => $a !== '')));
    }

    /**
     * recipient() for accept / reject: an account with no verified address
     * at all is refused (403) rather than answered with 404.
     *
     * @throws InvitationRecipientNotEligibleException
     */
    public function recipientWithVerifiedAddress(int $wordPressUserId): VerifiedInvitationRecipient
    {
        $recipient = $this->recipient($wordPressUserId);

        if (! $recipient->hasVerifiedAddress()) {
            throw new InvitationRecipientNotEligibleException('The account has no verified address.');
        }

        return $recipient;
    }

    public function isAddressedTo(VerifiedInvitationRecipient $recipient, ParticipantInvitation $invitation): bool
    {
        return $this->isEmailAddressedTo($recipient, $invitation->email());
    }

    /**
     * The same check for any invited address - also used for an
     * organization invitation (GetMyOrganizationInvitationUseCase).
     */
    public function isEmailAddressedTo(VerifiedInvitationRecipient $recipient, string $invitedEmail): bool
    {
        $address = InvitationEmailAddress::normalize($invitedEmail);

        if ($address === '' || ! in_array($address, $recipient->verifiedAddresses(), true)) {
            return false;
        }

        try {
            $owner = $this->findPersonByEmail->execute($invitedEmail);
        } catch (AmbiguousPersonEmailException $exception) {
            return false;
        }

        return $owner !== null && $owner->id === $recipient->person()->id()->toString();
    }
}
