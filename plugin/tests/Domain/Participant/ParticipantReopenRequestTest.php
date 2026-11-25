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
 * R14-B §5.2 / §7.4 (U13 A案, N1): a re-request reuses the row -
 * CANCELLED / REJECTED / WITHDRAWN / INACTIVE -> PENDING; never from
 * ACTIVE / PENDING / DRAFT. Id, enrollmentSource, consent and createdAt are
 * kept; requestedAt becomes this request's time.
 */
final class ParticipantReopenRequestTest extends TestCase
{
    private function participant(string $status, array $overrides = []): Participant
    {
        $overrides += [
            'enrollmentSource' => ParticipantEnrollmentSource::invitation(),
            'consentSource' => ParticipantConsentSource::invitation(),
            'consentedAt' => new DateTimeImmutable('2026-10-01T00:00:00+00:00'),
            'requestedAt' => new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        ];

        return Participant::reconstitute(
            ParticipantId::generate(),
            ProductionId::generate(),
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast(),
            ParticipantStatus::fromString($status),
            new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-08-02T00:00:00+00:00'),
            null,
            null,
            $overrides['enrollmentSource'],
            $overrides['consentSource'],
            $overrides['consentedAt'],
            $overrides['requestedAt']
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reopenable(): array
    {
        return [
            'CANCELLED' => [ParticipantStatus::CANCELLED],
            'REJECTED' => [ParticipantStatus::REJECTED],
            'WITHDRAWN' => [ParticipantStatus::WITHDRAWN],
            'INACTIVE' => [ParticipantStatus::INACTIVE],
        ];
    }

    /**
     * @dataProvider reopenable
     */
    public function test_the_same_row_becomes_pending_again(string $status): void
    {
        $participant = $this->participant($status);
        $id = $participant->id()->toString();
        $createdAt = $participant->createdAt();
        $consentedAt = $participant->consentedAt();
        $requestedAt = new DateTimeImmutable('2026-11-17T09:00:00+00:00');

        $participant->reopenRequest($requestedAt);

        $this->assertSame(ParticipantStatus::PENDING, $participant->status()->toString());
        $this->assertSame($id, $participant->id()->toString());
        $this->assertSame(ParticipantEnrollmentSource::INVITATION, $participant->enrollmentSource()->toString(), 'enrollmentSource is never changed');
        $this->assertSame(ParticipantConsentSource::INVITATION, $participant->consentSource()->toString());
        $this->assertEquals($consentedAt, $participant->consentedAt(), 'consent is recorded only on approval');
        $this->assertEquals($createdAt, $participant->createdAt());
        $this->assertEquals($requestedAt, $participant->requestedAt());
        $this->assertGreaterThan(new DateTimeImmutable('2026-08-02T00:00:00+00:00'), $participant->updatedAt());
    }

    public function test_a_legacy_row_without_route_keeps_it_null(): void
    {
        $participant = $this->participant(ParticipantStatus::CANCELLED, [
            'enrollmentSource' => null,
            'consentSource' => null,
            'consentedAt' => null,
            'requestedAt' => null,
        ]);

        $participant->reopenRequest(new DateTimeImmutable('2026-11-17T09:00:00+00:00'));

        $this->assertNull($participant->enrollmentSource());
        $this->assertNull($participant->consentedAt());
        $this->assertNotNull($participant->requestedAt());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function notReopenable(): array
    {
        return [
            'ACTIVE' => [ParticipantStatus::ACTIVE],
            'PENDING' => [ParticipantStatus::PENDING],
            'DRAFT (N1: never turned into a request)' => [ParticipantStatus::DRAFT],
        ];
    }

    /**
     * @dataProvider notReopenable
     */
    public function test_it_is_refused_and_nothing_changes(string $status): void
    {
        $participant = $this->participant($status);
        $updatedAt = $participant->updatedAt();
        $requestedAt = $participant->requestedAt();

        try {
            $participant->reopenRequest(new DateTimeImmutable('2026-11-17T09:00:00+00:00'));
            $this->fail("{$status} must not be reopened.");
        } catch (InvalidArgumentException $e) {
            $this->assertSame($status, $participant->status()->toString());
            $this->assertEquals($updatedAt, $participant->updatedAt());
            $this->assertEquals($requestedAt, $participant->requestedAt());
        }
    }

    /** updatedAt moves only when the Participant actually changes. */
    public function test_reading_does_not_touch_updated_at(): void
    {
        $participant = $this->participant(ParticipantStatus::ACTIVE);
        $updatedAt = $participant->updatedAt();

        $participant->isConsentedActivePerson();
        $participant->hasRecordedConsent();
        $this->assertFalse($participant->withdraw() === false);

        $withdrawnAt = $participant->updatedAt();
        $this->assertGreaterThan($updatedAt, $withdrawnAt);
        $this->assertFalse($participant->withdraw(), 'A repeated withdrawal is idempotent');
        $this->assertSame($withdrawnAt, $participant->updatedAt(), 'An idempotent repeat leaves updatedAt alone');
    }
}
