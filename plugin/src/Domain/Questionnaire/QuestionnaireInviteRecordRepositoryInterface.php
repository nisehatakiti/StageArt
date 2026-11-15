<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\ReservationId;

interface QuestionnaireInviteRecordRepositoryInterface
{
    public function save(QuestionnaireInviteRecord $record): void;

    public function exists(PerformanceId $performanceId, ReservationId $reservationId): bool;
}
