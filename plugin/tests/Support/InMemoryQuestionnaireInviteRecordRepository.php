<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Questionnaire\QuestionnaireInviteRecord;
use StageArt\Domain\Questionnaire\QuestionnaireInviteRecordRepositoryInterface;
use StageArt\Domain\Reservation\ReservationId;

final class InMemoryQuestionnaireInviteRecordRepository implements QuestionnaireInviteRecordRepositoryInterface
{
    /** @var array<string, QuestionnaireInviteRecord> */
    private array $records = [];

    public function save(QuestionnaireInviteRecord $record): void
    {
        $this->records[$this->key($record->performanceId(), $record->reservationId())] = $record;
    }

    public function exists(PerformanceId $performanceId, ReservationId $reservationId): bool
    {
        return isset($this->records[$this->key($performanceId, $reservationId)]);
    }

    private function key(PerformanceId $performanceId, ReservationId $reservationId): string
    {
        return $performanceId->toString() . ':' . $reservationId->toString();
    }
}
