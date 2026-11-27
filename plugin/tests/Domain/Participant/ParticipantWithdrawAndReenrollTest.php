<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Participant;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantConsentSource;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B design doc §5.2: WITHDRAWN (the Person's own exit) and row reuse
 * on invitation acceptance (U13 A案).
 */
final class ParticipantWithdrawAndReenrollTest extends TestCase
{
    private function personWithStatus(string $status, ?ParticipantEnrollmentSource $enrollmentSource = null): Participant
    {
        return Participant::reconstitute(
            ParticipantId::generate(),
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast(),
            ParticipantStatus::fromString($status),
            new DateTimeImmutable('2026-01-01T00:00:00+09:00'),
            new DateTimeImmutable('2026-01-01T00:00:00+09:00'),
            'remarks',
            null,
            $enrollmentSource
        );
    }

    public function test_withdrawn_is_a_valid_participant_status(): void
    {
        $this->assertSame('WITHDRAWN', ParticipantStatus::fromString(ParticipantStatus::WITHDRAWN)->toString());
    }

    public function test_an_active_person_can_withdraw(): void
    {
        $participant = $this->personWithStatus(ParticipantStatus::ACTIVE);
        $id = $participant->id();

        $changed = $participant->withdraw();

        $this->assertTrue($changed);
        $this->assertSame(ParticipantStatus::WITHDRAWN, $participant->status()->toString());
        $this->assertTrue($participant->id()->equals($id));
        $this->assertSame('remarks', $participant->remarks());
    }

    public function test_withdrawing_again_is_an_idempotent_no_op(): void
    {
        $participant = $this->personWithStatus(ParticipantStatus::WITHDRAWN);
        $updatedAt = $participant->updatedAt();

        $changed = $participant->withdraw();

        $this->assertFalse($changed);
        $this->assertSame(ParticipantStatus::WITHDRAWN, $participant->status()->toString());
        $this->assertEquals($updatedAt, $participant->updatedAt());
    }

    /** @return array<string, array{string}> */
    public static function notWithdrawable(): array
    {
        return [
            'DRAFT' => [ParticipantStatus::DRAFT],
            'INACTIVE' => [ParticipantStatus::INACTIVE],
            'CANCELLED' => [ParticipantStatus::CANCELLED],
            'PENDING' => [ParticipantStatus::PENDING],
            'REJECTED' => [ParticipantStatus::REJECTED],
        ];
    }

    /** @dataProvider notWithdrawable */
    public function test_only_an_active_participant_can_withdraw(string $status): void
    {
        $participant = $this->personWithStatus($status);

        try {
            $participant->withdraw();
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame($status, $participant->status()->toString());
        }
    }

    public function test_a_non_person_participant_cannot_withdraw(): void
    {
        $nameOnly = Participant::createNameOnly(ProductionId::generate(), '名前だけの人', ParticipantType::cast());

        $this->expectException(InvalidArgumentException::class);
        $nameOnly->withdraw();
    }

    /** @return array<string, array{string}> */
    public static function reenrollable(): array
    {
        return [
            'CANCELLED' => [ParticipantStatus::CANCELLED],
            'WITHDRAWN' => [ParticipantStatus::WITHDRAWN],
            'REJECTED' => [ParticipantStatus::REJECTED],
            'INACTIVE' => [ParticipantStatus::INACTIVE],
            'PENDING (superseded join request)' => [ParticipantStatus::PENDING],
        ];
    }

    /** @dataProvider reenrollable */
    public function test_reenroll_reactivates_the_same_row_and_keeps_its_id_and_enrollment_source(string $status): void
    {
        $participant = $this->personWithStatus($status, ParticipantEnrollmentSource::joinRequest());
        $id = $participant->id();

        $participant->reenroll();

        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertTrue($participant->id()->equals($id));
        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString());
    }

    public function test_reenroll_keeps_a_null_enrollment_source_null_for_existing_data(): void
    {
        $participant = $this->personWithStatus(ParticipantStatus::CANCELLED);

        $participant->reenroll();

        $this->assertNull($participant->enrollmentSource());
    }

    /** §5.2: acceptance = reenroll() + recordConsent(); enrollmentSource still unchanged. */
    public function test_reenroll_then_record_consent_marks_the_person_consented(): void
    {
        $participant = $this->personWithStatus(ParticipantStatus::WITHDRAWN, ParticipantEnrollmentSource::invitation());
        $at = new DateTimeImmutable('2026-11-20T10:00:00+09:00');

        $participant->reenroll();
        $participant->recordConsent(ParticipantConsentSource::invitation(), $at);

        $this->assertTrue($participant->isActivePerson());
        $this->assertEquals($at, $participant->consentedAt());
        $this->assertSame(ParticipantEnrollmentSource::INVITATION, $participant->enrollmentSource()->toString());
    }

    /** N11: a DRAFT row is never turned ACTIVE by acceptance. */
    public function test_reenroll_rejects_a_draft_row(): void
    {
        $participant = $this->personWithStatus(ParticipantStatus::DRAFT);

        try {
            $participant->reenroll();
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(ParticipantStatus::DRAFT, $participant->status()->toString());
        }
    }

    /** An already ACTIVE participant is not re-enrolled - only its consent is recorded. */
    public function test_reenroll_rejects_an_already_active_row(): void
    {
        $participant = $this->personWithStatus(ParticipantStatus::ACTIVE);

        $this->expectException(InvalidArgumentException::class);
        $participant->reenroll();
    }

    public function test_a_non_person_participant_cannot_be_reenrolled(): void
    {
        $organization = Participant::reconstitute(
            ParticipantId::generate(),
            ProductionId::generate(),
            ParticipantSubjectType::organization(),
            'organization-id',
            ParticipantType::staff(),
            ParticipantStatus::fromString(ParticipantStatus::CANCELLED),
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->expectException(InvalidArgumentException::class);
        $organization->reenroll();
    }
}
