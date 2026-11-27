<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Person\AmbiguousPersonEmailException;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\RateLimit\InvitationRateLimit;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * `POST /productions/{id}/participant-invitations`: an admin invites
 * someone by 氏名＋メールアドレス＋役割＋備考.
 *
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27;
 * the organization invitation's own shape, CreateOrganizationInvitationUseCase):
 * the administrator has confirmed the person's intention before inviting
 * them, and the person never accepts or rejects in StageArt.
 * - The address belongs to a verified account (FindPersonByEmailUseCase -
 *   verified addresses only): that Person is registered as a Participant at
 *   once (InvitationEnrollmentService, INVITATION_ENROLLED), `outcome`
 *   MEMBER_ADDED, no invitation is sent. Usable invitations to the same
 *   address and type are completed with it. Already an ACTIVE participant
 *   of the type: 409 already_exists, nothing changed.
 * - Otherwise an invitation: `outcome` INVITATION_SENT (a new invitation)
 *   or INVITATION_RESENT (a PENDING, unexpired invitation to the same
 *   address and type existed and was resent). It registers the Person once
 *   the address is verified (ResolveParticipantInvitationUseCase).
 * - One transaction, locked Production -> same-address invitations (FOR
 *   UPDATE) -> send record / Participant (§5.4), so two requests never
 *   create two PENDING invitations or two rows.
 * - Refused, writing nothing: Production COMPLETED / ARCHIVED / CANCELLED
 *   (409 production_closed); the 30-day per-address send limit (429
 *   send_limit, available_at); for a resend, also inviter_revoked /
 *   resend_limit (ResendParticipantInvitationUseCase). There is no block
 *   after a rejection any more (the rejection was removed).
 *   Addresses are compared after trim + lower-case (§6.4).
 * - A new invitation is valid for 7 days. Its token_hash column (NOT NULL,
 *   UNIQUE) gets the hash of 32 random bytes that are thrown away: no token
 *   is mailed, shown, logged or ever looked up (U5 C案).
 * - The e-mail is sent after the commit (InvitationSendService); a failed
 *   send does not undo the invitation or the counted send.
 */
final class CreateParticipantInvitationUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ParticipantInvitationRepositoryInterface $invitations;
    private ResendParticipantInvitationUseCase $resend;
    private ParticipantInvitationConditions $conditions;
    private InvitationSendService $sendService;
    private TransactionManagerInterface $transactions;
    /** R14-B stage 8 (§9.2); null only where a test leaves the limits out. */
    private ?InvitationRateLimit $rateLimit;
    /** Invitation policy 2026-11-27: null only where a test leaves the verified-account registration out. */
    private ?FindPersonByEmailUseCase $findPersonByEmail;
    private ?InvitationEnrollmentService $enrollment;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ParticipantInvitationRepositoryInterface $invitations,
        ResendParticipantInvitationUseCase $resend,
        ParticipantInvitationConditions $conditions,
        InvitationSendService $sendService,
        TransactionManagerInterface $transactions,
        ?InvitationRateLimit $rateLimit = null,
        ?FindPersonByEmailUseCase $findPersonByEmail = null,
        ?InvitationEnrollmentService $enrollment = null
    ) {
        $this->rateLimit = $rateLimit;
        $this->findPersonByEmail = $findPersonByEmail;
        $this->enrollment = $enrollment;
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

        // R14-B stage 8 (§9.2): counted once permission is confirmed, before
        // the input is checked and outside (before) the transaction; 429 when
        // over. A create that turns into a resend is still one attempt.
        if ($this->rateLimit !== null) {
            $this->rateLimit->assertSendAllowed($requester->id(), $production->id());
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

        $existingPerson = null;

        if ($this->findPersonByEmail !== null && $this->enrollment !== null) {
            try {
                $existingPerson = $this->findPersonByEmail->execute($email);
            } catch (AmbiguousPersonEmailException $exception) {
                // An address of more than one Person is never treated as one
                // account: an ordinary invitation, as for an unknown address.
                $existingPerson = null;
            }
        }

        if ($existingPerson !== null) {
            return $this->registerVerifiedAccount(
                $production->id(),
                PersonId::fromString($existingPerson->id),
                $requester->id(),
                $email,
                $name,
                $participantType,
                $command->remarks
            );
        }

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

    /**
     * The address belongs to a verified account: register that Person now.
     *
     * @throws ParticipantAlreadyExistsException already an ACTIVE participant of the type
     * @throws \StageArt\Application\Participant\ParticipantDraftExistsException a DRAFT row of the type
     */
    private function registerVerifiedAccount(
        ProductionId $productionId,
        PersonId $personId,
        PersonId $requesterId,
        string $email,
        string $name,
        ParticipantType $participantType,
        ?string $remarks
    ): CreateParticipantInvitationResult {
        $participantId = $this->transactions->run(
            function () use ($productionId, $personId, $requesterId, $email, $name, $participantType, $remarks): string {
                $now = new DateTimeImmutable();
                $locked = $this->productions->findByIdForUpdate($productionId);

                if ($locked === null) {
                    throw new ProductionNotFoundException($productionId->toString());
                }

                if ($this->conditions->isProductionClosed($locked)) {
                    throw new ParticipantInvitationProductionClosedException('The production is closed; invitations cannot be sent.');
                }

                $candidates = $this->invitations->findByProductionEmailAndTypeForUpdate($locked->id(), $email, $participantType);

                $enrolled = $this->enrollment->enroll(
                    $locked->id(),
                    $personId,
                    $participantType,
                    $name,
                    $remarks,
                    null,
                    $requesterId,
                    InvitationEnrollmentService::TRIGGER_INVITATION_CREATED,
                    $now
                );

                if (! $enrolled->becameActive) {
                    throw new ParticipantAlreadyExistsException('This person is already an active member of this Production.');
                }

                foreach ($candidates as $candidate) {
                    if ($candidate->isPending() && ! $candidate->isExpiredAt($now)) {
                        $candidate->completeByEnrollment($personId, $enrolled->participant->id(), $now);
                        $this->invitations->save($candidate);
                    }
                }

                return $enrolled->participant->id()->toString();
            }
        );

        return CreateParticipantInvitationResult::memberAdded($participantId);
    }
}
