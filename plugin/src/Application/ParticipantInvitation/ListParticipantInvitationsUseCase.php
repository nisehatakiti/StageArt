<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\ParticipantInvitation\InvitationEmailAddress;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSendRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Not explicitly named in this round's instruction (§9's minimum
 * UseCase list), but added to support §24's requirement that the member
 * management screen can show current PENDING invitations - there is no
 * other way to list a Production's invitations. Disclosed here as an
 * addition beyond the literal instruction, per this round's own
 * "既存仕様にない変更を行った場合は、必ずその理由と変更内容を明記してください"
 * requirement.
 *
 * PR #7 R14-B stage 4 remainder (R14B-4R, design doc §5.1 / §5.3 / §7.1 /
 * §11.3): each row also carries the §7.1 fields (ManagedParticipantInvitationResult).
 * - Who: canManageParticipants, as before (403 otherwise).
 * - A read: no lock (§5.4), nothing written, nobody notified, even when it
 *   derives PRODUCTION_CLOSED / INVITER_REVOKED (§5.1 / §11.5).
 * - No read per row: the invitations (one read), the Production's sends in
 *   the 30-day window (one read) and the current attempts (one read). The
 *   inviter's permission is judged once per distinct inviter, and only
 *   when a PENDING row within its expiry of an open Production needs it.
 *   reinvite_available_at comes from the invitations already read.
 */
final class ListParticipantInvitationsUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ParticipantInvitationConditions $conditions;
    private InvitationSendService $sendService;
    private ParticipantInvitationSendRepositoryInterface $sends;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ParticipantInvitationConditions $conditions,
        InvitationSendService $sendService,
        ParticipantInvitationSendRepositoryInterface $sends
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->conditions = $conditions;
        $this->sendService = $sendService;
        $this->sends = $sends;
    }

    /**
     * @return ManagedParticipantInvitationResult[]
     */
    public function execute(ListParticipantInvitationsQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can view invitations.'
            );
        }

        $invitations = $this->invitations->findByProductionId($production->id());

        if ($invitations === []) {
            return [];
        }

        $now = new DateTimeImmutable();
        $limits = $this->sendService->sendLimitsFor(
            $production->id(),
            array_map(static fn (ParticipantInvitation $invitation): string => $invitation->email(), $invitations),
            $now
        );
        $currentSends = $this->sends->recordsByIds(
            $production->id(),
            array_values(array_filter(array_map(static fn (ParticipantInvitation $invitation): ?string => $invitation->currentSendId(), $invitations)))
        );
        $latestRejections = self::latestRejectionsByAddress($invitations);
        $inviterCanManage = [];
        $results = [];

        foreach ($invitations as $invitation) {
            $address = InvitationEmailAddress::normalize($invitation->email());
            $current = $invitation->currentSendId() !== null ? ($currentSends[$invitation->currentSendId()] ?? null) : null;
            $isRejected = $invitation->status()->toString() === ParticipantInvitationStatus::REJECTED;
            $rejectedAt = $isRejected ? ($latestRejections[$address] ?? null) : null;

            $results[] = new ManagedParticipantInvitationResult(
                ParticipantInvitationResult::fromDomain($invitation),
                $this->effectiveStatus($invitation, $production, $now, $inviterCanManage),
                self::format($invitation->respondedAt()),
                $invitation->closeReason() !== null ? $invitation->closeReason()->toString() : null,
                self::format($invitation->closedAt()),
                $invitation->resendCount(),
                $invitation->remainingResends(),
                $current !== null ? $current->displayStatusAt($now) : null,
                $invitation->currentSendId() !== null ? self::format($invitation->lastSentAt()) : null,
                $limits[$address] ?? null,
                $rejectedAt !== null ? self::format($rejectedAt->add(new DateInterval(CreateParticipantInvitationUseCase::REJECTION_BLOCK))) : null
            );
        }

        return $results;
    }

    /**
     * §5.1: the one effective_status of a row.
     *
     * @param array<string, bool> $inviterCanManage memo, per inviter Person id
     */
    private function effectiveStatus(ParticipantInvitation $invitation, Production $production, DateTimeImmutable $now, array &$inviterCanManage): string
    {
        $status = $invitation->status()->toString();

        if ($status === ParticipantInvitationStatus::CANCELLED) {
            return ListMyParticipantInvitationsUseCase::effectiveStatusOfStoredCancellation($invitation->closeReason());
        }

        if ($status !== ParticipantInvitationStatus::PENDING) {
            return $status;
        }

        if ($invitation->isExpiredAt($now)) {
            return 'EXPIRED';
        }

        if ($this->conditions->isProductionClosed($production)) {
            return 'PRODUCTION_CLOSED';
        }

        $inviter = $invitation->invitedByPersonId()->toString();

        if (! array_key_exists($inviter, $inviterCanManage)) {
            $inviterCanManage[$inviter] = $this->conditions->inviterCanManage($invitation, $production);
        }

        return $inviterCanManage[$inviter] ? ParticipantInvitationStatus::PENDING : 'INVITER_REVOKED';
    }

    /**
     * D6 / §5.3: the latest rejection per normalised address of the
     * Production (any type), as latestRejectionAt() finds it for the create
     * API - from the invitations already read (they are all of this
     * Production).
     *
     * @param ParticipantInvitation[] $invitations
     * @return array<string, DateTimeImmutable>
     */
    private static function latestRejectionsByAddress(array $invitations): array
    {
        $latest = [];

        foreach ($invitations as $invitation) {
            if ($invitation->status()->toString() !== ParticipantInvitationStatus::REJECTED || $invitation->respondedAt() === null) {
                continue;
            }

            $address = InvitationEmailAddress::normalize($invitation->email());

            if (! isset($latest[$address]) || $invitation->respondedAt() > $latest[$address]) {
                $latest[$address] = $invitation->respondedAt();
            }
        }

        return $latest;
    }

    private static function format(?DateTimeImmutable $value): ?string
    {
        return $value !== null ? $value->format(DATE_ATOM) : null;
    }
}
