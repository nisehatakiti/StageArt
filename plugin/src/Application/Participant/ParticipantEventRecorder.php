<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantEvent\ParticipantEventRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;

/**
 * R14-B §6.3 / §7.4: the one place that writes the Participant audit log.
 * Called inside the business transaction, after the change it records, so
 * the event and the change commit or roll back together (a failed INSERT
 * throws and rolls the change back).
 *
 * Only ids and state-transition facts are recorded - never an e-mail
 * address, a name (invited_name / display_name / a Person's name) or a
 * token (ParticipantEvent refuses such detail keys).
 */
final class ParticipantEventRecorder
{
    private ParticipantEventRepositoryInterface $events;

    public function __construct(ParticipantEventRepositoryInterface $events)
    {
        $this->events = $events;
    }

    /**
     * §11.5: the write that first detected PRODUCTION_CLOSED /
     * INVITER_REVOKED. `detectedBy` is ACCEPT or REJECT;
     * primary_manager_notify_attempted means the one notification attempt
     * was decided in this transaction (not that it was delivered).
     */
    public function invitationClosed(
        ParticipantInvitation $invitation,
        string $detectedBy,
        bool $primaryManagerNotifyAttempted,
        PersonId $actorPersonId,
        DateTimeImmutable $at
    ): void {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::INVITATION_CLOSED,
            $invitation->productionId(),
            $at,
            null,
            $invitation->id(),
            null,
            null,
            $actorPersonId,
            null,
            [
                'close_reason' => $invitation->closeReason() !== null ? $invitation->closeReason()->toString() : null,
                'detected_by' => $detectedBy,
                'primary_manager_notify_attempted' => $primaryManagerNotifyAttempted,
            ]
        ));
    }

    /**
     * §5.2 / §4.7: the invitee accepted. previous_status is the Participant
     * row's status before the acceptance (null = no row, a new one was
     * created); reused_participant / superseded_join_request tell a reuse
     * and a pending join request taken over by the acceptance.
     */
    public function invitationAccepted(
        ParticipantInvitation $invitation,
        Participant $participant,
        ?string $previousStatus,
        DateTimeImmutable $at
    ): void {
        $person = PersonId::fromString($participant->subjectId());

        $this->append(ParticipantEvent::record(
            ParticipantEvent::ACCEPTED,
            $invitation->productionId(),
            $at,
            $participant->id(),
            $invitation->id(),
            null,
            ParticipantEvent::PATH_INVITATION,
            $person,
            $person,
            [
                'previous_status' => $previousStatus,
                'reused_participant' => $previousStatus !== null,
                'superseded_join_request' => $previousStatus === ParticipantStatus::PENDING,
            ]
        ));
    }

    /** §6.3: an already ACTIVE Participant's consent recorded by an acceptance. */
    public function consentRecorded(ParticipantInvitation $invitation, Participant $participant, DateTimeImmutable $at): void
    {
        $person = PersonId::fromString($participant->subjectId());

        $this->append(ParticipantEvent::record(
            ParticipantEvent::CONSENT_RECORDED,
            $invitation->productionId(),
            $at,
            $participant->id(),
            $invitation->id(),
            null,
            ParticipantEvent::PATH_INVITATION,
            $person,
            $person,
            ['consent_source' => ParticipantEvent::PATH_INVITATION]
        ));
    }

    /** §7.2 reject: previous_status is the invitation's status before it (PENDING / CANCELLED). */
    public function invitationRejected(
        ParticipantInvitation $invitation,
        PersonId $inviteePersonId,
        string $previousStatus,
        DateTimeImmutable $at
    ): void {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::REJECTED,
            $invitation->productionId(),
            $at,
            null,
            $invitation->id(),
            null,
            null,
            $inviteePersonId,
            $inviteePersonId,
            [
                'previous_status' => $previousStatus,
                'close_reason' => $invitation->closeReason() !== null ? $invitation->closeReason()->toString() : null,
            ]
        ));
    }

    /** §5.2: a join request (a new row, or a reused one - previous_status set). */
    public function joinRequested(Participant $participant, ?string $previousStatus, DateTimeImmutable $at): void
    {
        $person = PersonId::fromString($participant->subjectId());

        $this->append(ParticipantEvent::record(
            ParticipantEvent::JOIN_REQUESTED,
            $participant->productionId(),
            $at,
            $participant->id(),
            null,
            null,
            ParticipantEvent::PATH_JOIN_REQUEST,
            $person,
            $person,
            [
                'previous_status' => $previousStatus,
                'reused_participant' => $previousStatus !== null,
            ]
        ));
    }

    public function joinApproved(Participant $participant, PersonId $actorPersonId, bool $consentRecorded, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::JOIN_APPROVED,
            $participant->productionId(),
            $at,
            $participant->id(),
            null,
            null,
            ParticipantEvent::PATH_JOIN_REQUEST,
            $actorPersonId,
            PersonId::fromString($participant->subjectId()),
            [
                'previous_status' => ParticipantStatus::PENDING,
                'consent_recorded' => $consentRecorded,
            ]
        ));
    }

    public function joinRejected(Participant $participant, PersonId $actorPersonId, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::JOIN_REJECTED,
            $participant->productionId(),
            $at,
            $participant->id(),
            null,
            null,
            null,
            $actorPersonId,
            PersonId::fromString($participant->subjectId()),
            ['previous_status' => ParticipantStatus::PENDING]
        ));
    }

    public function withdrawn(Participant $participant, DateTimeImmutable $at): void
    {
        $person = PersonId::fromString($participant->subjectId());

        $this->append(ParticipantEvent::record(
            ParticipantEvent::WITHDRAWN,
            $participant->productionId(),
            $at,
            $participant->id(),
            null,
            null,
            null,
            $person,
            $person,
            ['previous_status' => ParticipantStatus::ACTIVE]
        ));
    }

    /** §4.6: one delegate row made INACTIVE; `reason` is one of ParticipantDelegateRoleReleaser's reasons. */
    public function delegateDeactivated(
        ProductionDelegate $delegate,
        ?Participant $participant,
        PersonId $actorPersonId,
        string $reason,
        DateTimeImmutable $at
    ): void {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::DELEGATE_DEACTIVATED,
            $delegate->productionId(),
            $at,
            $participant !== null ? $participant->id() : null,
            null,
            $delegate->id(),
            null,
            $actorPersonId,
            $delegate->personId(),
            [
                'reason' => $reason,
                'role' => $delegate->role()->toString(),
            ]
        ));
    }

    /** Stage 4 (§5.2): a manager added a NAME_ONLY row (no Person, no consent). */
    public function nameOnlyAdded(Participant $participant, PersonId $actorPersonId, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::NAME_ONLY_ADDED,
            $participant->productionId(),
            $at,
            $participant->id(),
            null,
            null,
            'MANAGER_RECORD',
            $actorPersonId,
            null,
            ['participant_type' => $participant->participantType()->toString()]
        ));
    }

    /** Stage 4 (§5.2 N10): DELETE /participants/{id} - ACTIVE / INACTIVE -> CANCELLED. */
    public function removedByManager(Participant $participant, string $previousStatus, PersonId $actorPersonId, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::REMOVED_BY_MANAGER,
            $participant->productionId(),
            $at,
            $participant->id(),
            null,
            null,
            null,
            $actorPersonId,
            self::subjectPersonOf($participant),
            ['previous_status' => $previousStatus]
        ));
    }

    /** Stage 4 (§5.2 N6): PUT /participants/{id} - ACTIVE -> INACTIVE. */
    public function deactivated(Participant $participant, PersonId $actorPersonId, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::DEACTIVATED,
            $participant->productionId(),
            $at,
            $participant->id(),
            null,
            null,
            null,
            $actorPersonId,
            self::subjectPersonOf($participant),
            ['previous_status' => ParticipantStatus::ACTIVE]
        ));
    }

    /** Stage 4 (§5.2 N7): PUT /participants/{id} actually changed the type. */
    public function typeChanged(Participant $participant, string $fromType, PersonId $actorPersonId, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::TYPE_CHANGED,
            $participant->productionId(),
            $at,
            $participant->id(),
            null,
            null,
            null,
            $actorPersonId,
            self::subjectPersonOf($participant),
            ['from_type' => $fromType, 'to_type' => $participant->participantType()->toString()]
        ));
    }

    /** Stage 4 (§11.3): a send attempt accepted by the create API (INITIAL). */
    public function invited(ParticipantInvitation $invitation, string $sendId, PersonId $actorPersonId, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::INVITED,
            $invitation->productionId(),
            $at,
            null,
            $invitation->id(),
            null,
            null,
            $actorPersonId,
            null,
            ['send_id' => $sendId, 'participant_type' => $invitation->participantType()->toString()]
        ));
    }

    /** Stage 4 (§11.3): a send attempt accepted as a resend (create of a pending one, or the resend API). */
    public function resent(ParticipantInvitation $invitation, string $sendId, PersonId $actorPersonId, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::RESENT,
            $invitation->productionId(),
            $at,
            null,
            $invitation->id(),
            null,
            null,
            $actorPersonId,
            null,
            ['send_id' => $sendId, 'resend_count' => $invitation->resendCount()]
        ));
    }

    /** Stage 4 (§6.3 / §11.3): written with the FAILED send record, after the commit of the send itself. */
    public function sendFailed(ProductionId $productionId, ParticipantInvitationId $invitationId, string $sendId, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            ParticipantEvent::SEND_FAILED,
            $productionId,
            $at,
            null,
            $invitationId,
            null,
            null,
            null,
            null,
            ['send_id' => $sendId]
        ));
    }

    /** Stage 4 (§4.6): a new delegate row (DELEGATE_APPOINTED) or an INACTIVE one made ACTIVE again (DELEGATE_REACTIVATED). */
    public function delegateAppointed(ProductionDelegate $delegate, PersonId $actorPersonId, bool $reactivated, DateTimeImmutable $at): void
    {
        $this->append(ParticipantEvent::record(
            $reactivated ? ParticipantEvent::DELEGATE_REACTIVATED : ParticipantEvent::DELEGATE_APPOINTED,
            $delegate->productionId(),
            $at,
            null,
            null,
            $delegate->id(),
            null,
            $actorPersonId,
            $delegate->personId(),
            ['role' => $delegate->role()->toString()]
        ));
    }

    private static function subjectPersonOf(Participant $participant): ?PersonId
    {
        return $participant->subjectType()->toString() === ParticipantSubjectType::PERSON
            ? PersonId::fromString($participant->subjectId())
            : null;
    }

    private function append(ParticipantEvent $event): void
    {
        $this->events->append($event);
    }
}
