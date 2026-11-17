<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantEvent;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;

/**
 * R14-B §6.3 (D8): one append-only audit record of a Participant /
 * invitation / delegate state change (stageart_participant_events). It is
 * written in the same transaction as the change and never updated or
 * deleted by the application.
 *
 * It identifies everything by row id. An e-mail address, a name or a token
 * (plain or hashed) is never stored: `detail` (JSON) takes only the state
 * transition facts (previous_status, close_reason, reason, ...), and keys
 * that would carry personal data or a token are refused here.
 */
final class ParticipantEvent
{
    public const INVITATION_CLOSED = 'INVITATION_CLOSED';
    public const ACCEPTED = 'ACCEPTED';
    public const REJECTED = 'REJECTED';
    public const JOIN_REQUESTED = 'JOIN_REQUESTED';
    public const JOIN_APPROVED = 'JOIN_APPROVED';
    public const JOIN_REJECTED = 'JOIN_REJECTED';
    public const WITHDRAWN = 'WITHDRAWN';
    public const CONSENT_RECORDED = 'CONSENT_RECORDED';
    public const DELEGATE_DEACTIVATED = 'DELEGATE_DEACTIVATED';

    /** §6.3 event types this stage writes (the others belong to later stages). */
    private const TYPES = [
        self::INVITATION_CLOSED,
        self::ACCEPTED,
        self::REJECTED,
        self::JOIN_REQUESTED,
        self::JOIN_APPROVED,
        self::JOIN_REJECTED,
        self::WITHDRAWN,
        self::CONSENT_RECORDED,
        self::DELEGATE_DEACTIVATED,
    ];

    /** §6.3 enrollment_path values. */
    public const PATH_INVITATION = 'INVITATION';
    public const PATH_JOIN_REQUEST = 'JOIN_REQUEST';

    private const PATHS = [self::PATH_INVITATION, self::PATH_JOIN_REQUEST, 'MANAGER_RECORD'];

    /** detail keys that would carry personal data or a token. */
    private const FORBIDDEN_DETAIL_KEYS = [
        'email', 'e_mail', 'mail', 'name', 'invited_name', 'display_name', 'family_name', 'given_name',
        'token', 'token_hash', 'remarks',
    ];

    private string $id;
    private ProductionId $productionId;
    private ?ParticipantId $participantId;
    private ?ParticipantInvitationId $invitationId;
    private ?ProductionDelegateId $delegateId;
    private string $eventType;
    private ?string $enrollmentPath;
    private ?PersonId $actorPersonId;
    private ?PersonId $subjectPersonId;
    /** @var array<string, scalar|null> */
    private array $detail;
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, scalar|null> $detail
     */
    private function __construct(
        string $id,
        ProductionId $productionId,
        ?ParticipantId $participantId,
        ?ParticipantInvitationId $invitationId,
        ?ProductionDelegateId $delegateId,
        string $eventType,
        ?string $enrollmentPath,
        ?PersonId $actorPersonId,
        ?PersonId $subjectPersonId,
        array $detail,
        DateTimeImmutable $createdAt
    ) {
        if (! in_array($eventType, self::TYPES, true)) {
            throw new InvalidArgumentException("Invalid participant event type: {$eventType}");
        }

        if ($enrollmentPath !== null && ! in_array($enrollmentPath, self::PATHS, true)) {
            throw new InvalidArgumentException("Invalid enrollment path: {$enrollmentPath}");
        }

        foreach ($detail as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_DETAIL_KEYS, true)) {
                throw new InvalidArgumentException("The audit detail must not carry '{$key}'.");
            }

            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("The audit detail '{$key}' must be a scalar value.");
            }
        }

        $this->id = $id;
        $this->productionId = $productionId;
        $this->participantId = $participantId;
        $this->invitationId = $invitationId;
        $this->delegateId = $delegateId;
        $this->eventType = $eventType;
        $this->enrollmentPath = $enrollmentPath;
        $this->actorPersonId = $actorPersonId;
        $this->subjectPersonId = $subjectPersonId;
        $this->detail = $detail;
        $this->createdAt = $createdAt;
    }

    /**
     * @param array<string, scalar|null> $detail
     */
    public static function record(
        string $eventType,
        ProductionId $productionId,
        DateTimeImmutable $at,
        ?ParticipantId $participantId = null,
        ?ParticipantInvitationId $invitationId = null,
        ?ProductionDelegateId $delegateId = null,
        ?string $enrollmentPath = null,
        ?PersonId $actorPersonId = null,
        ?PersonId $subjectPersonId = null,
        array $detail = []
    ): self {
        return new self(
            ParticipantId::generate()->toString(),
            $productionId,
            $participantId,
            $invitationId,
            $delegateId,
            $eventType,
            $enrollmentPath,
            $actorPersonId,
            $subjectPersonId,
            $detail,
            $at
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function participantId(): ?ParticipantId
    {
        return $this->participantId;
    }

    public function invitationId(): ?ParticipantInvitationId
    {
        return $this->invitationId;
    }

    public function delegateId(): ?ProductionDelegateId
    {
        return $this->delegateId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function enrollmentPath(): ?string
    {
        return $this->enrollmentPath;
    }

    public function actorPersonId(): ?PersonId
    {
        return $this->actorPersonId;
    }

    public function subjectPersonId(): ?PersonId
    {
        return $this->subjectPersonId;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function detail(): array
    {
        return $this->detail;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
