<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

use InvalidArgumentException;
use StageArt\Domain\Person\PersonRepositoryInterface;

/**
 * StageArt Authentication Phase 6: always acts on the caller's own
 * Person (resolved from requestedByWordPressUserId, never a request
 * parameter) - matching ChangePasswordUseCase/RequestEmailVerificationUseCase's
 * established "self-service" pattern in this codebase, so no one can
 * update another Person's name. Both Email and Google-authenticated
 * callers reach this the same way (set-name.tsx is shown to either,
 * per this Phase's gate) - nothing here is provider-specific.
 */
final class UpdatePersonNameUseCase
{
    private PersonRepositoryInterface $people;

    public function __construct(PersonRepositoryInterface $people)
    {
        $this->people = $people;
    }

    public function execute(UpdatePersonNameCommand $command): UpdatePersonNameResult
    {
        $familyName = trim($command->familyName);
        $givenName = trim($command->givenName);

        if ($familyName === '' || $givenName === '') {
            throw new InvalidArgumentException('Both family_name and given_name are required.');
        }

        $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

        if (! $person) {
            throw new CurrentPersonNotFoundException('No StageArt Person is linked to this WordPress user.');
        }

        $person->setName($familyName, $givenName);
        $this->people->save($person);

        return new UpdatePersonNameResult($person->id()->toString(), $person->familyName(), $person->givenName());
    }
}
