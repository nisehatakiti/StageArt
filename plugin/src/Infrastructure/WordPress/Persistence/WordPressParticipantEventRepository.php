<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeZone;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantEvent\ParticipantEventRepositoryInterface;
use wpdb;

/**
 * PR #7 R14-B (design doc §6.3, D8): writes stageart_participant_events
 * (schema 1.49.0). INSERT only - this class has no update or delete. A
 * failed INSERT throws (WordPressCheckedWrites), so the state change it
 * records is rolled back with it; the exception never carries the
 * database's error text. detail is stored as JSON (NULL when empty);
 * created_at as UTC, like the other R14-B tables.
 */
final class WordPressParticipantEventRepository implements ParticipantEventRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_participant_events';
    }

    public function append(ParticipantEvent $event): void
    {
        $detail = $event->detail();

        WordPressCheckedWrites::insert($this->wpdb, $this->table, [
            'id' => $event->id(),
            'production_id' => $event->productionId()->toString(),
            'participant_id' => $event->participantId() !== null ? $event->participantId()->toString() : null,
            'invitation_id' => $event->invitationId() !== null ? $event->invitationId()->toString() : null,
            'delegate_id' => $event->delegateId() !== null ? $event->delegateId()->toString() : null,
            'event_type' => $event->eventType(),
            'enrollment_path' => $event->enrollmentPath(),
            'actor_person_id' => $event->actorPersonId() !== null ? $event->actorPersonId()->toString() : null,
            'subject_person_id' => $event->subjectPersonId() !== null ? $event->subjectPersonId()->toString() : null,
            'detail' => $detail === [] ? null : json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $event->createdAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }
}
