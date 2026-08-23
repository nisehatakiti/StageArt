<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Phase 4: the Mobile Client needs to know its own PersonId to compute
 * Personal Highlight client-side (Timetable.md's "Personal Highlight
 * Principle" - comparing a TimetableItem's participant_type/
 * target_person_ids against "my own" values is only possible once the
 * client knows which PersonId is "me"). No prior phase exposed this,
 * since every existing UseCase resolves the current Person internally
 * and never returns raw Person identity on its own.
 *
 * mobile-rn Blueprint v1.5 alignment Phase: emailVerified is a read-only
 * projection of EmailCredential.isEmailVerified() - never written here.
 * A UserAccount with no EmailCredential at all (Google-only) is reported
 * as emailVerified=true: "true" means "nothing about email verification
 * is blocking this account", not "an EmailCredential exists and is
 * verified" - Google's own auth already proves the user controls their
 * Google identity, so there is nothing for this flag to gate for them
 * (see AuthenticationUXClarifications.md and the Backend Phase 2
 * report's Google-vs-Email distinction). Only an UNVERIFIED
 * EmailCredential yields false.
 */
final class GetCurrentPersonUseCase
{
    private OrganizationAuthorizationService $authorization;
    private UserAccountRepositoryInterface $userAccounts;
    private EmailCredentialRepositoryInterface $emailCredentials;

    public function __construct(
        OrganizationAuthorizationService $authorization,
        UserAccountRepositoryInterface $userAccounts,
        EmailCredentialRepositoryInterface $emailCredentials
    ) {
        $this->authorization = $authorization;
        $this->userAccounts = $userAccounts;
        $this->emailCredentials = $emailCredentials;
    }

    public function execute(int $wordPressUserId): CurrentPersonResult
    {
        $person = $this->authorization->resolveCurrentPerson($wordPressUserId);

        if (! $person) {
            throw new CurrentPersonNotFoundException('No StageArt Person is linked to this WordPress user.');
        }

        $emailVerified = true;
        $userAccount = $this->userAccounts->findByPersonId($person->id());

        if ($userAccount) {
            $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());

            if ($credential) {
                $emailVerified = $credential->isEmailVerified();
            }
        }

        return new CurrentPersonResult(
            $person->id()->toString(),
            $person->wordPressUserId(),
            $emailVerified,
            $person->familyName(),
            $person->givenName()
        );
    }
}
