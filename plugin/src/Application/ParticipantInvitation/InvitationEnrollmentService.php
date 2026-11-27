<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use StageArt\Application\Participant\ParticipantDelegateRoleReleaser;
use StageArt\Application\Participant\ParticipantDraftExistsException;
use StageArt\Application\Participant\ParticipantEnrollmentOutcome;
use StageArt\Application\Participant\ParticipantEnrollmentService;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27):
 * registers an invited Person as a Participant - no acceptance by the
 * person, no consent recorded. Shared by the two moments it happens:
 * - CreateParticipantInvitationUseCase: the invited address belongs to a
 *   verified account (trigger INVITATION_CREATED, no invitation row);
 * - ResolveParticipantInvitationUseCase: the invited address was verified
 *   or signed in with Google (trigger ADDRESS_VERIFIED, the invitation).
 *
 * The caller has started the transaction and locked the Production first
 * (R14-B §5.4 order). When the row is already ACTIVE nothing is written and
 * the outcome says so (previousStatus ACTIVE); otherwise the row is created
 * or re-activated, the Person's ACTIVE delegate rows go dormant when they
 * thereby became a participant (R14-B U15, unchanged), and
 * INVITATION_ENROLLED is recorded in the same transaction.
 */
final class InvitationEnrollmentService
{
    public const TRIGGER_INVITATION_CREATED = 'INVITATION_CREATED';
    public const TRIGGER_ADDRESS_VERIFIED = 'ADDRESS_VERIFIED';

    private ParticipantEnrollmentService $enrollment;
    private ParticipantDelegateRoleReleaser $delegateRoles;
    private ParticipantEventRecorder $events;

    public function __construct(
        ParticipantEnrollmentService $enrollment,
        ParticipantDelegateRoleReleaser $delegateRoles,
        ParticipantEventRecorder $events
    ) {
        $this->enrollment = $enrollment;
        $this->delegateRoles = $delegateRoles;
        $this->events = $events;
    }

    /**
     * @throws ParticipantDraftExistsException a DRAFT row of the type exists (nothing changed)
     */
    public function enroll(
        ProductionId $productionId,
        PersonId $personId,
        ParticipantType $participantType,
        ?string $displayName,
        ?string $remarks,
        ?ParticipantInvitation $invitation,
        PersonId $actorPersonId,
        string $trigger,
        DateTimeImmutable $now
    ): ParticipantEnrollmentOutcome {
        $outcome = $this->enrollment->enrollByInvitation($productionId, $personId, $participantType, $displayName, $remarks);

        if ($outcome->previousStatus === ParticipantStatus::ACTIVE) {
            return $outcome;
        }

        if ($outcome->personBecameParticipant) {
            $this->delegateRoles->release(
                $productionId,
                $personId,
                $actorPersonId,
                ParticipantDelegateRoleReleaser::DORMANT_ON_ENROLLMENT,
                $outcome->participant,
                $now
            );
        }

        $this->events->invitationEnrolled($invitation, $outcome->participant, $outcome->previousStatus, $actorPersonId, $trigger, $now);

        return $outcome;
    }
}
