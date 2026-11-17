<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Domain\Participant\Participant;

/**
 * What ParticipantEnrollmentService did with the one row of the
 * (production, PERSON, person, type) key.
 */
final class ParticipantEnrollmentOutcome
{
    public Participant $participant;
    /** The row's status before this operation; null = there was no row and one was created. */
    public ?string $previousStatus;
    /** true = this row became ACTIVE from a non-ACTIVE state or from no row. */
    public bool $becameActive;
    /**
     * true = the Person became an ACTIVE participant of the Production with
     * this: this row became ACTIVE and the Person had no other ACTIVE PERSON
     * row (of the other type) there. Delegate rows are per Person, so only
     * then are they dormant (§4.6 DORMANT_ON_ENROLLMENT, 「参加者でなかった
     * 人物が…参加者になる」).
     */
    public bool $personBecameParticipant;

    public function __construct(Participant $participant, ?string $previousStatus, bool $becameActive, bool $personBecameParticipant = false)
    {
        $this->participant = $participant;
        $this->previousStatus = $previousStatus;
        $this->becameActive = $becameActive;
        $this->personBecameParticipant = $personBecameParticipant;
    }

    public function created(): bool
    {
        return $this->previousStatus === null;
    }
}
