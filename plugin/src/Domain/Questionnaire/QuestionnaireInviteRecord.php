<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use DateTimeImmutable;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\ReservationId;

/**
 * §25/§50: "同じPerformance + Reservationについてアンケート依頼Emailを二重
 * 送信しない" - a purely operational "did we already email this booking for
 * this Performance" fact, unrelated to answer data. §25's own closing line
 * is explicit that this must never become a path from Response back to a
 * Reservation: nothing in this class - or anywhere in the Questionnaire
 * Response persistence path - ever reads from or joins against this table.
 * It exists solely so SendQuestionnaireInvitesForPerformanceUseCase can
 * skip a (performanceId, reservationId) pair it has already mailed.
 */
final class QuestionnaireInviteRecord
{
    private PerformanceId $performanceId;
    private ReservationId $reservationId;
    private DateTimeImmutable $sentAt;

    private function __construct(PerformanceId $performanceId, ReservationId $reservationId, DateTimeImmutable $sentAt)
    {
        $this->performanceId = $performanceId;
        $this->reservationId = $reservationId;
        $this->sentAt = $sentAt;
    }

    public static function create(PerformanceId $performanceId, ReservationId $reservationId): self
    {
        return new self($performanceId, $reservationId, new DateTimeImmutable());
    }

    public static function reconstitute(PerformanceId $performanceId, ReservationId $reservationId, DateTimeImmutable $sentAt): self
    {
        return new self($performanceId, $reservationId, $sentAt);
    }

    public function performanceId(): PerformanceId
    {
        return $this->performanceId;
    }

    public function reservationId(): ReservationId
    {
        return $this->reservationId;
    }

    public function sentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }
}
