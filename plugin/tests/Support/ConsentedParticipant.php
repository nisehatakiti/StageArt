<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use DateTimeImmutable;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B §4.6 (stage 4): a ProductionDelegate row grants its Role only
 * while the Person is an ACTIVE PERSON Participant of the Production with
 * recorded consent. Tests written before stage 4 that set up a delegate
 * to exercise some other rule call this to give that delegate the
 * participation the rule now requires (an ACTIVE row that accepted an
 * invitation) - their own expectations stay as they were.
 */
final class ConsentedParticipant
{
    public static function seed(
        ParticipantRepositoryInterface $participants,
        ProductionId $productionId,
        PersonId $personId,
        string $participantType = ParticipantType::STAFF
    ): Participant {
        $participant = Participant::enrollFromInvitation(
            $productionId,
            $personId->toString(),
            ParticipantType::fromString($participantType),
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            null,
            null
        );
        $participants->save($participant);

        return $participant;
    }
}
