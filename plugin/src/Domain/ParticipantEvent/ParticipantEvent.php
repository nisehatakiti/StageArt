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
    /** R14-B stage 4 (§6.3): invitation sends, manager changes of a Participant, delegate appointments. */
    public const INVITED = 'INVITED';
    public const RESENT = 'RESENT';
    public const SEND_FAILED = 'SEND_FAILED';
    public const NAME_ONLY_ADDED = 'NAME_ONLY_ADDED';
    public const REMOVED_BY_MANAGER = 'REMOVED_BY_MANAGER';
    public const DEACTIVATED = 'DEACTIVATED';
    public const TYPE_CHANGED = 'TYPE_CHANGED';
    public const DELEGATE_APPOINTED = 'DELEGATE_APPOINTED';
    public const DELEGATE_REACTIVATED = 'DELEGATE_REACTIVATED';
    /** R14-B stage 4 review fix (§5.1 / §6.3): a manager cancelled a PENDING invitation (close_reason MANAGER). */
    public const INVITATION_CANCELLED = 'INVITATION_CANCELLED';
    /**
     * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27):
     * the invited Person was registered automatically - at invitation time
     * (a verified account with the invited address) or when the invited
     * address was verified / signed in with Google. Not a consent.
     * ACCEPTED / REJECTED / CONSENT_RECORDED are no longer written (the
     * acceptance and rejection were removed) and stay readable as history.
     */
    public const INVITATION_ENROLLED = 'INVITATION_ENROLLED';

    /** §6.3 event types (all of them are written now). */
    private const TYPES = [
        self::INVITATION_ENROLLED,
        self::INVITATION_CANCELLED,
        self::INVITED,
        self::RESENT,
        self::SEND_FAILED,
        self::NAME_ONLY_ADDED,
        self::REMOVED_BY_MANAGER,
        self::DEACTIVATED,
        self::TYPE_CHANGED,
        self::DELEGATE_APPOINTED,
        self::DELEGATE_REACTIVATED,
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
