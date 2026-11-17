<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/**
 * R14-B §5.1 / §7.2 `GET /me/participant-invitations` (D7, N5, U7): the
 * invitations addressed to the logged-in Person's verified addresses.
 * A read: it writes nothing and notifies nobody, even when it derives
 * PRODUCTION_CLOSED / INVITER_REVOKED.
 *
 * - Candidates come from the verified addresses only (login credential and
 *   notification address, verified); the Repository keeps only exact
 *   §6.4 matches and InvitationRecipientVerifier confirms each one (an
 *   address that resolves to another Person or to several is dropped).
 * - Listed (§5.1 table "本人の一覧に出す"): PENDING within its expiry -
 *   effective_status PENDING (can_accept), or PRODUCTION_CLOSED /
 *   INVITER_REVOKED (reject only) - and CANCELLED(PRODUCTION_CLOSED /
 *   INVITER_REVOKED) within its expiry (reject only). Nothing expired,
 *   answered, CONSUMED or CANCELLED(MANAGER / no reason) is listed.
 * - Derived order: EXPIRED -> PRODUCTION_CLOSED -> INVITER_REVOKED ->
 *   PENDING.
 * - invited_by_display_name is the inviter's name as recorded on the
 *   Production (their Participant display name), otherwise
 *   「公演の管理者」 - never the inviter's Person name.
 * - already_participant: the invitee already has an ACTIVE Participant of
 *   the invitation's type in that Production (their own information).
 */
final class ListMyParticipantInvitationsUseCase
{
    private const INVITED_BY_FALLBACK = '公演の管理者';

    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private ProjectRepositoryInterface $projects;
    private OrganizationRepositoryInterface $organizations;
    private ParticipantRepositoryInterface $participants;
    private InvitationRecipientVerifier $recipients;
    private ParticipantInvitationConditions $conditions;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        ProjectRepositoryInterface $projects,
        OrganizationRepositoryInterface $organizations,
        ParticipantRepositoryInterface $participants,
        InvitationRecipientVerifier $recipients,
        ParticipantInvitationConditions $conditions
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->projects = $projects;
        $this->organizations = $organizations;
        $this->participants = $participants;
        $this->recipients = $recipients;
        $this->conditions = $conditions;
    }

    /**
     * @return MyParticipantInvitationResult[]
     */
    public function execute(ListMyParticipantInvitationsQuery $query): array
    {
        $recipient = $this->recipients->recipient($query->requestedByWordPressUserId);

        if (! $recipient->hasVerifiedAddress()) {
            return [];
        }

        $now = new DateTimeImmutable();
        $results = [];

        foreach ($this->invitations->findByVerifiedEmailCandidates($recipient->verifiedAddresses()) as $invitation) {
            if (! self::isListable($invitation, $now) || ! $this->recipients->isAddressedTo($recipient, $invitation)) {
                continue;
            }

            $production = $this->productions->findById($invitation->productionId());

            if ($production === null) {
                continue;
            }

            $results[] = $this->toResult($invitation, $production, $recipient->person()->id()->toString());
        }

        usort(
            $results,
            static fn (MyParticipantInvitationResult $a, MyParticipantInvitationResult $b): int => [$a->expiresAt, $a->id] <=> [$b->expiresAt, $b->id]
        );

        return $results;
    }

    private static function isListable(ParticipantInvitation $invitation, DateTimeImmutable $now): bool
    {
        if ($invitation->isExpiredAt($now)) {
            return false;
        }

        $status = $invitation->status()->toString();

        if ($status === ParticipantInvitationStatus::PENDING) {
            return true;
        }

        return $status === ParticipantInvitationStatus::CANCELLED
            && $invitation->closeReason() !== null
            && $invitation->closeReason()->allowsInviteeRejection();
    }

    private function toResult(ParticipantInvitation $invitation, Production $production, string $personId): MyParticipantInvitationResult
    {
        if ($invitation->status()->toString() === ParticipantInvitationStatus::CANCELLED) {
            $effective = ParticipantInvitationStatus::CANCELLED;
            $unavailable = $invitation->closeReason()->toString();
        } elseif ($this->conditions->isProductionClosed($production)) {
            $effective = 'PRODUCTION_CLOSED';
            $unavailable = 'PRODUCTION_CLOSED';
        } elseif (! $this->conditions->inviterCanManage($invitation, $production)) {
            $effective = 'INVITER_REVOKED';
            $unavailable = 'INVITER_REVOKED';
        } else {
            $effective = ParticipantInvitationStatus::PENDING;
            $unavailable = null;
        }

        $existing = $this->participants->findByProductionAndSubject(
            $production->id(),
            ParticipantSubjectType::person(),
            $personId,
            $invitation->participantType()
        );

        return new MyParticipantInvitationResult(
            $invitation->id()->toString(),
            $production->id()->toString(),
            $production->name()->toString(),
            $this->organizationName($production),
            $invitation->participantType()->toString(),
            $this->invitedByDisplayName($invitation, $production),
            $invitation->expiresAt()->format(DATE_ATOM),
            $effective,
            $existing !== null && $existing->isActive(),
            $unavailable === null,
            true,
            $unavailable
        );
    }

    private function organizationName(Production $production): ?string
    {
        $project = $this->projects->findById($production->projectId());

        if ($project === null) {
            return null;
        }

        $organization = $this->organizations->findById($project->organizationId());

        return $organization !== null ? $organization->name()->toString() : null;
    }

    private function invitedByDisplayName(ParticipantInvitation $invitation, Production $production): string
    {
        foreach ($this->participants->findByProductionId($production->id()) as $participant) {
            if ($participant->subjectType()->equals(ParticipantSubjectType::person())
                && $participant->subjectId() === $invitation->invitedByPersonId()->toString()
                && $participant->displayName() !== null
                && trim($participant->displayName()) !== ''
            ) {
                return $participant->displayName();
            }
        }

        return self::INVITED_BY_FALLBACK;
    }
}
