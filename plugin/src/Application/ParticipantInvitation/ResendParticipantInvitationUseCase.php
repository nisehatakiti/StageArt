<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * `POST /participant-invitations/{id}/resend`, and the resend step that
 * `POST /productions/{id}/participant-invitations` uses when a PENDING
 * invitation to the same address and type exists (§5.3).
 *
 * PR #7 R14-B stage 4 (design doc §5.3 / §7.1 / §11.3, D3 / D5 / D6):
 * - One transaction, locked Production -> invitation -> send record (§5.4).
 * - Refused, writing nothing: not PENDING (422 not_pending, as before),
 *   Production COMPLETED / ARCHIVED / CANCELLED (409 production_closed),
 *   the inviter can no longer manage participants (409 inviter_revoked -
 *   no state change, no notification, §11.5), MAX_RESENDS reached (429
 *   resend_limit), the 30-day per-address limit reached (429 send_limit
 *   with available_at), the email_key keyring incomplete (500).
 * - Otherwise the resend is counted (resend_count, send record), the
 *   expiry moves to 7 days from now, the token is NOT changed (the e-mail
 *   carries none), and the e-mail is sent after the commit
 *   (InvitationSendService). A failed send leaves the resend counted.
 */
final class ResendParticipantInvitationUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ParticipantInvitationConditions $conditions;
    private InvitationSendService $sendService;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ParticipantInvitationConditions $conditions,
        InvitationSendService $sendService,
        TransactionManagerInterface $transactions
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->conditions = $conditions;
        $this->sendService = $sendService;
        $this->transactions = $transactions;
    }

    public function execute(ResendParticipantInvitationCommand $command): ParticipantInvitationResult
    {
        $invitationId = ParticipantInvitationId::fromString($command->invitationId);
        $invitation = $this->invitations->findById($invitationId);

        if (! $invitation) {
            throw new ParticipantInvitationNotFoundException($command->invitationId);
        }

        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantInvitationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById($invitation->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($invitation->productionId()->toString());
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantInvitationAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can resend this invitation.'
            );
        }

        [$locked, $pending] = $this->transactions->run(function () use ($invitationId, $production, $requester): array {
            $lockedProduction = $this->productions->findByIdForUpdate($production->id());

            if ($lockedProduction === null) {
                throw new ProductionNotFoundException($production->id()->toString());
            }

            $locked = $this->invitations->findByIdForUpdate($invitationId);

            if ($locked === null) {
                throw new ParticipantInvitationNotFoundException($invitationId->toString());
            }

            if (! $locked->isPending()) {
                throw new ParticipantInvitationNotPendingException('Only a PENDING ParticipantInvitation can be resent.');
            }

            if ($this->conditions->isProductionClosed($lockedProduction)) {
                throw new ParticipantInvitationProductionClosedException('The production is closed; invitations cannot be sent.');
            }

            return [$locked, $this->resendLocked($locked, $lockedProduction, $requester, new DateTimeImmutable())];
        });

        $this->sendService->deliver($pending);

        return ParticipantInvitationResult::fromDomain($locked);
    }

    /**
     * The resend itself, inside the caller's transaction with the Production
     * and the invitation locked; the Production is known not to be closed.
     *
     * @throws ParticipantInvitationInviterRevokedException
     * @throws ParticipantInvitationResendLimitException
     * @throws ParticipantInvitationSendLimitException
     * @throws EmailKeyNotConfiguredException
     */
    public function resendLocked(
        ParticipantInvitation $invitation,
        Production $production,
        Person $requester,
        DateTimeImmutable $now
    ): PendingInvitationSend {
        if (! $this->conditions->inviterCanManage($invitation, $production)) {
            throw new ParticipantInvitationInviterRevokedException(
                'The inviter can no longer manage participants; cancel this invitation and invite again.'
            );
        }

        if ($invitation->hasReachedResendLimit()) {
            throw new ParticipantInvitationResendLimitException('This invitation has been resent the maximum number of times.');
        }

        // Checked before the invitation changes, so a refusal leaves it as it was.
        $this->sendService->assertWithinLimit($invitation->productionId(), $invitation->email(), $now);

        $invitation->resend($now);

        return $this->sendService->accept(
            $invitation,
            ParticipantInvitationSend::KIND_RESEND,
            $requester->id(),
            $production->name()->toString(),
            $now
        );
    }
}
