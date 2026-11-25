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
 * R14-B design doc §4.5 (D2): enrolment route vs the Person's own consent.
 */
final class ParticipantEnrollmentAndConsentTest extends TestCase
{
    private function reconstitutePerson(
        string $status,
        ?ParticipantEnrollmentSource $enrollmentSource = null,
        ?ParticipantConsentSource $consentSource = null,
        ?DateTimeImmutable $consentedAt = null
    ): Participant {
        return Participant::reconstitute(
            ParticipantId::generate(),
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast(),
            ParticipantStatus::fromString($status),
            new DateTimeImmutable('2026-01-01T00:00:00+09:00'),
            new DateTimeImmutable('2026-01-01T00:00:00+09:00'),
            null,
            null,
            $enrollmentSource,
            $consentSource,
            $consentedAt
        );
    }

    public function test_enroll_from_invitation_creates_an_active_person_with_invitation_enrollment_and_consent(): void
    {
        $personId = PersonId::generate()->toString();
        $acceptedAt = new DateTimeImmutable('2026-11-20T10:00:00+09:00');

        $participant = Participant::enrollFromInvitation(
            ProductionId::generate(),
            $personId,
            ParticipantType::staff(),
            $acceptedAt,
            '  remarks  ',
            '山田 太郎'
        );

        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
        $this->assertSame(ParticipantSubjectType::PERSON, $participant->subjectType()->toString());
        $this->assertSame($personId, $participant->subjectId());
        $this->assertSame(ParticipantEnrollmentSource::INVITATION, $participant->enrollmentSource()->toString());
        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString());
        $this->assertEquals($acceptedAt, $participant->consentedAt());
        $this->assertSame('remarks', $participant->remarks());
        $this->assertSame('山田 太郎', $participant->displayName());
        $this->assertTrue($participant->isConsentedActivePerson());
    }

    public function test_request_participation_records_join_request_enrollment_without_consent(): void
    {
        $participant = Participant::requestParticipation(
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );

        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString());
        $this->assertNull($participant->consentSource());
        $this->assertNull($participant->consentedAt());
        $this->assertFalse($participant->hasRecordedConsent());
    }

    public function test_create_name_only_records_manager_record_enrollment_and_no_consent(): void
    {
        $participant = Participant::createNameOnly(ProductionId::generate(), '名前だけの人', ParticipantType::cast());

        $this->assertSame(ParticipantEnrollmentSource::MANAGER_RECORD, $participant->enrollmentSource()->toString());
        $this->assertNull($participant->consentSource());
        $this->assertNull($participant->consentedAt());
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status()->toString());
    }

    /** §4.5 / §12: existing (pre-R14-B) rows reconstitute with NULLs and are never treated as consented. */
    public function test_existing_data_with_null_sources_is_not_consented(): void
    {
        $participant = $this->reconstitutePerson(ParticipantStatus::ACTIVE);

        $this->assertNull($participant->enrollmentSource());
        $this->assertNull($participant->consentSource());
        $this->assertNull($participant->consentedAt());
        $this->assertFalse($participant->hasRecordedConsent());
        $this->assertFalse($participant->isConsentedActivePerson());
    }

    public function test_reconstitute_without_the_new_arguments_keeps_the_previous_signature_working(): void
    {
        $participant = Participant::reconstitute(
            ParticipantId::generate(),
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast(),
            ParticipantStatus::active(),
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->assertNull($participant->enrollmentSource());
        $this->assertFalse($participant->hasRecordedConsent());
    }

    public function test_reconstitute_preserves_enrollment_and_consent(): void
    {
        $consentedAt = new DateTimeImmutable('2026-11-01T09:00:00+09:00');

        $participant = $this->reconstitutePerson(
            ParticipantStatus::ACTIVE,
            ParticipantEnrollmentSource::joinRequest(),
            ParticipantConsentSource::joinRequest(),
            $consentedAt
        );

        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString());
        $this->assertSame(ParticipantConsentSource::JOIN_REQUEST, $participant->consentSource()->toString());
        $this->assertEquals($consentedAt, $participant->consentedAt());
        $this->assertTrue($participant->isConsentedActivePerson());
    }

    public function test_reconstitute_rejects_a_consent_source_without_a_consent_time(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->reconstitutePerson(ParticipantStatus::ACTIVE, null, ParticipantConsentSource::invitation(), null);
    }

    /** §4.5: an existing (unconsented) ACTIVE participant who later accepts an invitation gets consent recorded; enrollmentSource is untouched. */
    public function test_record_consent_on_an_active_person_sets_source_and_time_without_touching_enrollment(): void
    {
        $participant = $this->reconstitutePerson(ParticipantStatus::ACTIVE);
        $at = new DateTimeImmutable('2026-11-20T12:00:00+09:00');

        $participant->recordConsent(ParticipantConsentSource::invitation(), $at);

        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString());
        $this->assertEquals($at, $participant->consentedAt());
        $this->assertNull($participant->enrollmentSource());
        $this->assertTrue($participant->isConsentedActivePerson());
    }

    /** §4.5: the most recent consent overwrites the previous one (history lives in the audit log). */
    public function test_record_consent_overwrites_with_the_most_recent_consent(): void
    {
        $participant = $this->reconstitutePerson(
            ParticipantStatus::ACTIVE,
            ParticipantEnrollmentSource::joinRequest(),
            ParticipantConsentSource::joinRequest(),
            new DateTimeImmutable('2026-10-01T09:00:00+09:00')
        );
        $later = new DateTimeImmutable('2026-11-20T09:00:00+09:00');

        $participant->recordConsent(ParticipantConsentSource::invitation(), $later);

        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString());
        $this->assertEquals($later, $participant->consentedAt());
        $this->assertSame(ParticipantEnrollmentSource::JOIN_REQUEST, $participant->enrollmentSource()->toString());
    }

    /** @return array<string, array{string}> */
    public static function nonActiveStatuses(): array
    {
        return [
            'DRAFT' => [ParticipantStatus::DRAFT],
            'INACTIVE' => [ParticipantStatus::INACTIVE],
            'CANCELLED' => [ParticipantStatus::CANCELLED],
            'PENDING' => [ParticipantStatus::PENDING],
            'REJECTED' => [ParticipantStatus::REJECTED],
        ];
    }

    /** @dataProvider nonActiveStatuses */
    public function test_record_consent_requires_an_active_participant(string $status): void
    {
        $participant = $this->reconstitutePerson($status);

        try {
            $participant->recordConsent(ParticipantConsentSource::invitation(), new DateTimeImmutable());
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            $this->assertNull($participant->consentSource());
            $this->assertNull($participant->consentedAt());
        }
    }

    public function test_record_consent_is_not_applicable_to_name_only_or_organization_participants(): void
    {
        $nameOnly = Participant::createNameOnly(ProductionId::generate(), '名前だけの人', ParticipantType::cast());
        $organization = Participant::create(
            ProductionId::generate(),
            ParticipantSubjectType::organization(),
            'organization-id',
            ParticipantType::staff()
        );

        foreach ([$nameOnly, $organization] as $participant) {
            try {
                $participant->recordConsent(ParticipantConsentSource::invitation(), new DateTimeImmutable());
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->assertFalse($participant->hasRecordedConsent());
            }
        }
    }

    /** §4.6 base condition is only about the Participant's own state. */
    public function test_consented_active_person_requires_person_active_and_consent(): void
    {
        $consentedInactive = $this->reconstitutePerson(
            ParticipantStatus::INACTIVE,
            null,
            ParticipantConsentSource::invitation(),
            new DateTimeImmutable()
        );

        $this->assertTrue($consentedInactive->hasRecordedConsent());
        $this->assertFalse($consentedInactive->isConsentedActivePerson());
    }

    public function test_source_value_objects_reject_unknown_values(): void
    {
        foreach ([fn () => ParticipantEnrollmentSource::fromString('UNKNOWN'), fn () => ParticipantConsentSource::fromString('MANAGER_RECORD')] as $make) {
            try {
                $make();
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('Invalid', $exception->getMessage());
            }
        }
    }
}
