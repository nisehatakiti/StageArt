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

    public function test_request_participation_starts_pending(): void
    {
        $participant = Participant::requestParticipation(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );

        $this->assertTrue($participant->isPending());
        $this->assertSame(ParticipantStatus::PENDING, $participant->status()->toString());
    }

    public function test_approving_a_pending_request_activates_it(): void
    {
        $participant = Participant::requestParticipation(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );

        $participant->approve();

        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertFalse($participant->isPending());
    }

    public function test_rejecting_a_pending_request_marks_it_rejected(): void
    {
        $participant = Participant::requestParticipation(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );

        $participant->reject();

        $this->assertSame(ParticipantStatus::REJECTED, $participant->status()->toString());
    }

    public function test_an_already_active_participant_cannot_be_approved_again(): void
    {
        $participant = Participant::create(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );

        $this->expectException(\InvalidArgumentException::class);

        $participant->approve();
    }

    public function test_create_name_only_generates_a_placeholder_subject_id_and_carries_the_display_name(): void
    {
        $participant = Participant::createNameOnly(ProductionId::generate(), '山田太郎', ParticipantType::cast());

        $this->assertSame(ParticipantSubjectType::NAME_ONLY, $participant->subjectType()->toString());
        $this->assertSame('山田太郎', $participant->displayName());
        $this->assertNotEmpty($participant->subjectId());
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
    }

    public function test_create_name_only_rejects_an_empty_display_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Participant::createNameOnly(ProductionId::generate(), '   ', ParticipantType::cast());
    }

    public function test_change_remarks_sets_and_normalizes_blank_to_null(): void
    {
        $participant = Participant::create(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );

        $participant->changeRemarks('チームA');
        $this->assertSame('チームA', $participant->remarks());

        $participant->changeRemarks('   ');
        $this->assertNull($participant->remarks());
    }
}
