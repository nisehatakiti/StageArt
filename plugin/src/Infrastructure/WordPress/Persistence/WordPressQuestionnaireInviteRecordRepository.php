<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use RuntimeException;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Questionnaire\QuestionnaireInviteRecord;
use StageArt\Domain\Questionnaire\QuestionnaireInviteRecordRepositoryInterface;
use StageArt\Domain\Reservation\ReservationId;
use wpdb;

final class WordPressQuestionnaireInviteRecordRepository implements QuestionnaireInviteRecordRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_questionnaire_invite_sent';
    }

    public function save(QuestionnaireInviteRecord $record): void
    {
        $result = $this->wpdb->insert($this->table, [
            'performance_id' => $record->performanceId()->toString(),
            'reservation_id' => $record->reservationId()->toString(),
            'sent_at' => $record->sentAt()->format('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            throw new RuntimeException('Failed to insert QuestionnaireInviteRecord.');
        }
    }

    public function exists(PerformanceId $performanceId, ReservationId $reservationId): bool
    {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE performance_id = %s AND reservation_id = %s",
                $performanceId->toString(),
                $reservationId->toString()
            )
        );

        return ((int) $count) > 0;
    }
}
