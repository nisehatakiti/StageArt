<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;

/** Mirrors ResendParticipantInvitationUseCase's own shape - rotates the
 * current token in place (no new OrganizationInvitation row). */
final class ResendOrganizationInvitationUseCase
{
    private OrganizationInvitationRepositoryInterface $invitations;
    private OrganizationRepositoryInterface $organizations;
    private OrganizationAuthorizationService $authorization;
    private OrganizationInvitationMailerInterface $mailer;

    public function __construct(
        OrganizationInvitationRepositoryInterface $invitations,
        OrganizationRepositoryInterface $organizations,
        OrganizationAuthorizationService $authorization,
        OrganizationInvitationMailerInterface $mailer
    ) {
        $this->invitations = $invitations;
        $this->organizations = $organizations;
        $this->authorization = $authorization;
        $this->mailer = $mailer;
    }

    public function execute(ResendOrganizationInvitationCommand $command): OrganizationInvitationResult
    {
        $invitation = $this->invitations->findById(OrganizationInvitationId::fromString($command->invitationId));

        if (! $invitation) {
            throw new OrganizationInvitationNotFoundException($command->invitationId);
        }

        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organization = $this->organizations->findById($invitation->organizationId());

        if (! $organization) {
            throw new OrganizationNotFoundException($invitation->organizationId()->toString());
        }

        if (! $this->authorization->hasDelegateArea($requester, $invitation->organizationId(), DelegateArea::MEMBERS)) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can resend this invitation.');
        }

        if (! $invitation->isPending()) {
            throw new OrganizationInvitationNotPendingException('Only a PENDING OrganizationInvitation can be resent.');
        }

        $tokenValue = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenValue);
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval(OrganizationInvitation::TOKEN_LIFETIME_SPEC));

        $invitation->rotateToken($tokenHash, $expiresAt);
        $this->invitations->save($invitation);

        $this->mailer->sendInvitationEmail($invitation->email(), $organization->name()->toString(), $tokenValue);

        return OrganizationInvitationResult::fromDomain($invitation);
    }
}
