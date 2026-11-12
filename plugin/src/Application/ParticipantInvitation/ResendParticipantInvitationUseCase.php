<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * §20/§11: rotates the current token in place (no new
 * ParticipantInvitation row) - the same "invalidate the old, issue a
 * new one" shape RequestPasswordResetUseCase already uses, but here as
 * an in-place field update rather than a separate consume()+create()
 * across two Token rows, since ParticipantInvitation embeds its own
 * current token instead of delegating to a separate Token Entity (see
 * ParticipantInvitation's own docblock for why). Also the Use Case
 * CreateParticipantInvitationUseCase delegates to when it finds an
 * existing, still-usable PENDING invitation for the same
 * (production, email, participantType) tuple (§11's duplicate-invitation
 * rule), so both the explicit REST resend action and the "duplicate
 * invite request" path share one implementation.
 */
final class ResendParticipantInvitationUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ParticipantInvitationMailerInterface $mailer;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ParticipantInvitationMailerInterface $mailer
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->mailer = $mailer;
    }

    public function execute(ResendParticipantInvitationCommand $command): ParticipantInvitationResult
    {
        $invitation = $this->invitations->findById(ParticipantInvitationId::fromString($command->invitationId));

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

        if (! $invitation->isPending()) {
            throw new ParticipantInvitationNotPendingException(
                'Only a PENDING ParticipantInvitation can be resent.'
            );
        }

        $tokenValue = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenValue);
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval(ParticipantInvitation::TOKEN_LIFETIME_SPEC));

        $invitation->rotateToken($tokenHash, $expiresAt);
        $this->invitations->save($invitation);

        $this->mailer->sendInvitationEmail($invitation->email(), $production->name()->toString(), $tokenValue);

        return ParticipantInvitationResult::fromDomain($invitation);
    }
}
