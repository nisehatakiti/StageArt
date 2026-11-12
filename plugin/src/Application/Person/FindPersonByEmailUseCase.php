<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * StageArt メール招待によるProductionParticipant追加機能: the integrated
 * "does an existing StageArt Person already own this email" search
 * confirmed necessary during Domain investigation, because
 * EmailCredentialRepositoryInterface::findByEmail() alone misses a
 * Google-only Person (ExternalIdentity never stores email - see its own
 * docblock). Checks two sources only, per this round's explicit scope:
 *
 * 1. EmailCredential (password-login email) -> UserAccount -> Person.
 * 2. NotificationEmail, but ONLY rows with verified=true (see
 *    NotificationEmailRepositoryInterface::findVerifiedByEmail()'s own
 *    docblock for why an unverified one must never be trusted here).
 *
 * Deliberately does NOT touch ExternalIdentity, and does NOT add an
 * email field to Person/UserAccount - both explicitly out of scope this
 * round.
 *
 * Deliberately takes no requester/Production - this is a pure Domain
 * search with no authorization concept of its own. Both call sites
 * (SearchPersonByEmailUseCase for the REST endpoint,
 * CreateParticipantInvitationUseCase internally) are responsible for
 * checking authorization themselves before calling this.
 */
final class FindPersonByEmailUseCase
{
    private EmailCredentialRepositoryInterface $emailCredentials;
    private UserAccountRepositoryInterface $userAccounts;
    private NotificationEmailRepositoryInterface $notificationEmails;
    private PersonRepositoryInterface $people;

    public function __construct(
        EmailCredentialRepositoryInterface $emailCredentials,
        UserAccountRepositoryInterface $userAccounts,
        NotificationEmailRepositoryInterface $notificationEmails,
        PersonRepositoryInterface $people
    ) {
        $this->emailCredentials = $emailCredentials;
        $this->userAccounts = $userAccounts;
        $this->notificationEmails = $notificationEmails;
        $this->people = $people;
    }

    /**
     * @throws AmbiguousPersonEmailException when EmailCredential and
     *   verified NotificationEmail resolve to more than one distinct
     *   Person for this same email address - an anomalous state this
     *   method refuses to silently resolve by picking one.
     */
    public function execute(string $email): ?PersonSummaryResult
    {
        $personIds = [];

        $credential = $this->emailCredentials->findByEmail($email);

        if ($credential !== null) {
            $userAccount = $this->userAccounts->findById($credential->userAccountId());

            if ($userAccount !== null) {
                $personIds[$userAccount->personId()->toString()] = $userAccount->personId()->toString();
            }
        }

        foreach ($this->notificationEmails->findVerifiedByEmail($email) as $notificationEmail) {
            $personIds[$notificationEmail->personId()->toString()] = $notificationEmail->personId()->toString();
        }

        if (count($personIds) === 0) {
            return null;
        }

        if (count($personIds) > 1) {
            throw new AmbiguousPersonEmailException(
                "The email address {$email} resolves to more than one StageArt Person."
            );
        }

        $person = $this->people->findById(PersonId::fromString(reset($personIds)));

        return $person !== null ? PersonSummaryResult::fromDomain($person) : null;
    }
}
