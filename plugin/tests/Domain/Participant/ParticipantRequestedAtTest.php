<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Participant;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B §6.1 (N4), stage 2: the Participant holds requestedAt so the
 * Repository can store and restore it. Setting it on a (re-)request is the
 * Application step's; in this stage no factory sets it.
 */
final class ParticipantRequestedAtTest extends TestCase
{
    public function test_reconstitute_keeps_the_request_time(): void
    {
        $requestedAt = new DateTimeImmutable('2026-11-10T09:00:00+00:00');

        $participant = Participant::reconstitute(
            ParticipantId::generate(),
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast(),
            ParticipantStatus::fromString(ParticipantStatus::PENDING),
            new DateTimeImmutable('2026-11-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-11-10T09:00:00+00:00'),
            null,
            null,
            null,
            null,
            null,
            $requestedAt
        );

        $this->assertEquals($requestedAt, $participant->requestedAt());
        $this->assertNotEquals($participant->createdAt(), $participant->requestedAt());
        $this->assertFalse($participant->hasRecordedConsent(), 'A request time is not a recorded consent');
    }

    public function test_reconstitute_without_it_reads_null(): void
    {
        $participant = Participant::reconstitute(
            ParticipantId::generate(),
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast(),
            ParticipantStatus::fromString(ParticipantStatus::PENDING),
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->assertNull($participant->requestedAt());
    }

    public function test_no_factory_sets_it_in_this_stage(): void
    {
        $productionId = ProductionId::generate();
        $personId = PersonId::generate()->toString();

        $this->assertNull(Participant::requestParticipation($productionId, ParticipantSubjectType::person(), $personId, ParticipantType::cast())->requestedAt());
        $this->assertNull(Participant::create($productionId, ParticipantSubjectType::person(), $personId, ParticipantType::cast())->requestedAt());
    }
}
