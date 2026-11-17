<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * `POST /productions/{id}/participant-invitations`: an admin invites
 * someone by 氏名＋メールアドレス＋役割＋備考.
 *
 * PR #7 R14-B stage 4 (design doc §4.1 / §5.3 / §7.1 / §9.2 / §11.3):
 * - Always an invitation, whoever the address belongs to: no Person is
 *   looked up by the address (FindPersonByEmailUseCase is not used), no
 *   Participant is added directly, and no in-app notification is created.
 *   The response is the same for a registered and an unregistered address:
 *   `outcome` INVITATION_SENT (a new invitation) or INVITATION_RESENT (a
 *   PENDING, unexpired invitation to the same address and type existed and
 *   was resent), and the invitation as the admin entered it - never a
 *   Person id, a Person's name or whether an account exists.
 * - One transaction, locked Production -> same-address invitations (FOR
 *   UPDATE) -> send record (§5.4), so two requests never create two
 *   PENDING invitations.
 * - Refused, writing nothing: Production COMPLETED / ARCHIVED / CANCELLED
 *   (409 production_closed); for a new invitation, a rejection of an
 *   invitation of this Production to the same address (any type) less than
 *   30 days ago (409 recently_rejected, available_at); the 30-day
 *   per-address send limit (429 send_limit, available_at); for a resend,
 *   also inviter_revoked / resend_limit (ResendParticipantInvitationUseCase).
 *   Addresses are compared after trim + lower-case (§6.4).
 * - A new invitation is valid for 7 days. Its token_hash column (NOT NULL,
 *   UNIQUE) gets the hash of 32 random bytes that are thrown away: no token
 *   is mailed, shown, logged or ever looked up (U5 C案).
 * - The e-mail is sent after the commit (InvitationSendService); a failed
 *   send does not undo the invitation or the counted send.
 */
final class CreateParticipantInvitationUseCase
{
    private const REJECTION_BLOCK = 'P30D';

    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ParticipantInvitationRepositoryInterface $invitations;
    private ResendParticipantInvitationUseCase $resend;
    private ParticipantInvitationConditions $conditions;
    private InvitationSendService $sendService;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ParticipantInvitationRepositoryInterface $invitations,
        ResendParticipantInvitationUseCase $resend,
        ParticipantInvitationConditions $conditions,
        InvitationSendService $sendService,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->invitations = $invitations;
        $this->resend = $resend;
        $this->conditions = $conditions;
        $this->sendService = $sendService;
        $this->transactions = $transactions;
    }

    public function execute(CreateParticipantInvitationCommand $command): CreateParticipantInvitationResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can invite a member.'
            );
        }

        $email = trim($command->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $name = trim($command->name);

        if ($name === '') {
            throw new InvalidArgumentException('name must not be empty.');
        }

        $participantType = ParticipantType::fromString($command->participantType);

        [$outcome, $invitation, $pending] = $this->transactions->run(
            function () use ($production, $requester, $email, $name, $participantType, $command): array {
                $now = new DateTimeImmutable();
                $locked = $this->productions->findByIdForUpdate($production->id());

                if ($locked === null) {
                    throw new ProductionNotFoundException($production->id()->toString());
                }

                if ($this->conditions->isProductionClosed($locked)) {
                    throw new ParticipantInvitationProductionClosedException('The production is closed; invitations cannot be sent.');
                }

                foreach ($this->invitations->findByProductionEmailAndTypeForUpdate($locked->id(), $email, $participantType) as $candidate) {
                    if ($candidate->isUsable()) {
                        return [
                            CreateParticipantInvitationResult::OUTCOME_INVITATION_RESENT,
                            $candidate,
                            $this->resend->resendLocked($candidate, $locked, $requester, $now),
                        ];
                    }
                }

                $rejectedAt = $this->invitations->latestRejectionAt($locked->id(), $email);

                if ($rejectedAt !== null) {
                    $availableAt = $rejectedAt->add(new DateInterval(self::REJECTION_BLOCK));

                    if ($availableAt > $now) {
                        throw new ParticipantInvitationRecentlyRejectedException($availableAt);
                    }
                }

                $invitation = ParticipantInvitation::create(
                    $locked->id(),
                    $email,
                    $name,
                    $requester->id(),
                    $participantType,
                    $command->remarks,
                    hash('sha256', random_bytes(32)),
                    ParticipantInvitation::expiresAtFrom($now)
                );

                return [
                    CreateParticipantInvitationResult::OUTCOME_INVITATION_SENT,
                    $invitation,
                    $this->sendService->accept(
                        $invitation,
                        ParticipantInvitationSend::KIND_INITIAL,
                        $requester->id(),
                        $locked->name()->toString(),
                        $now
                    ),
                ];
            }
        );

        $this->sendService->deliver($pending);

        return CreateParticipantInvitationResult::of($outcome, ParticipantInvitationResult::fromDomain($invitation));
    }
}
