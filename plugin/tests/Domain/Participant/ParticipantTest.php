<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Participant;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class ParticipantTest extends TestCase
{
    public function test_create_starts_active(): void
    {
        $subjectId = PersonId::generate()->toString();

        $participant = Participant::create(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            $subjectId,
            ParticipantType::cast()
        );

        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantSubjectType::PERSON, $participant->subjectType()->toString());
        $this->assertSame($subjectId, $participant->subjectId());
        $this->assertSame(ParticipantType::CAST, $participant->participantType()->toString());
    }

    public function test_lifecycle_transitions(): void
    {
        $participant = Participant::create(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::staff()
        );

        $participant->deactivate();
        $this->assertSame(ParticipantStatus::INACTIVE, $participant->status()->toString());

        $participant->activate();
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());

        $participant->cancel();
        $this->assertSame(ParticipantStatus::CANCELLED, $participant->status()->toString());
    }

    public function test_change_participant_type(): void
    {
        $participant = Participant::create(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );

        $participant->changeParticipantType(ParticipantType::staff());

        $this->assertSame(ParticipantType::STAFF, $participant->participantType()->toString());
    }

    public function test_organization_subject_type_is_supported(): void
    {
        $participant = Participant::create(
            ProductionId::generate(),
            ParticipantSubjectType::organization(),
            'some-organization-id',
            ParticipantType::staff()
        );

        $this->assertSame(ParticipantSubjectType::ORGANIZATION, $participant->subjectType()->toString());
    }
}
