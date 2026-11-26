<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Domain\Person\Person;

/**
 * The logged-in Person whose UserAccount is ACTIVE, with their verified
 * addresses (login EmailCredential and NotificationEmail, verified only),
 * each in the §6.4 normalised form. Never sent to the client.
 */
final class VerifiedInvitationRecipient
{
    private Person $person;
    /** @var string[] */
    private array $verifiedAddresses;

    /**
     * @param string[] $verifiedAddresses normalised
     */
    public function __construct(Person $person, array $verifiedAddresses)
    {
        $this->person = $person;
        $this->verifiedAddresses = array_values(array_unique($verifiedAddresses));
    }

    public function person(): Person
    {
        return $this->person;
    }

    /**
     * @return string[]
     */
    public function verifiedAddresses(): array
    {
        return $this->verifiedAddresses;
    }

    public function hasVerifiedAddress(): bool
    {
        return $this->verifiedAddresses !== [];
    }
}
