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
 * Repository can store and restore it. Stage 3 sets it on a request
 * (requestParticipation()) and a re-request (reopenRequest(), see
 * ParticipantReopenRequestTest); every other factory leaves it NULL.
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

    /**
     * Stage 3 (spec change from stage 2, design §5.2 "(行なし) → PENDING":
     * requested_at = the request time): a new join request records it; a
     * Participant that is not a request does not.
     */
    public function test_only_a_join_request_sets_it(): void
    {
        $productionId = ProductionId::generate();
        $personId = PersonId::generate()->toString();
        $at = new DateTimeImmutable('2026-11-17T09:00:00+00:00');

        $this->assertEquals($at, Participant::requestParticipation($productionId, ParticipantSubjectType::person(), $personId, ParticipantType::cast(), $at)->requestedAt());
        $this->assertNotNull(Participant::requestParticipation($productionId, ParticipantSubjectType::person(), $personId, ParticipantType::cast())->requestedAt());
        $this->assertNull(Participant::create($productionId, ParticipantSubjectType::person(), $personId, ParticipantType::cast())->requestedAt());
        $this->assertNull(Participant::enrollFromInvitation($productionId, $personId, ParticipantType::cast(), $at)->requestedAt());
    }
}
