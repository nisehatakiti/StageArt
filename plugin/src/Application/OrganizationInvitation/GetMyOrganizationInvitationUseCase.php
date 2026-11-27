<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use InvalidArgumentException;
use StageArt\Application\ParticipantInvitation\InvitationRecipientNotEligibleException;
use StageArt\Application\ParticipantInvitation\InvitationRecipientVerifier;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationStatus;

/**
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27):
 * `GET /me/organization-invitations/{id}` - the screen the organization
 * invitation mail's link opens (/organization-invitations/{id}), after
 * login. The same rule as GetMyParticipantInvitationUseCase: shown only
 * when the invited address is one of the logged-in Person's verified
 * addresses and resolves to them (InvitationRecipientVerifier); otherwise,
 * or when it does not exist, the same 404. A read: nothing is written.
 *
 * status: REGISTERED (an ACTIVE Membership of the organization), PENDING,
 * EXPIRED, CANCELLED or CLOSED (completed earlier, not a member now).
 */
final class GetMyOrganizationInvitationUseCase
{
    public const STATUS_REGISTERED = 'REGISTERED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_CLOSED = 'CLOSED';

    private InvitationRecipientVerifier $recipients;
    private OrganizationInvitationRepositoryInterface $invitations;
    private OrganizationRepositoryInterface $organizations;
    private MembershipRepositoryInterface $memberships;

    public function __construct(
        InvitationRecipientVerifier $recipients,
        OrganizationInvitationRepositoryInterface $invitations,
        OrganizationRepositoryInterface $organizations,
        MembershipRepositoryInterface $memberships
    ) {
        $this->recipients = $recipients;
        $this->invitations = $invitations;
        $this->organizations = $organizations;
        $this->memberships = $memberships;
    }

    /**
     * @return array{id: string, organization_id: string, organization_name: string, status: string}
     *
     * @throws InvitationRecipientNotEligibleException no Person / not an ACTIVE account (403)
     * @throws OrganizationInvitationNotFoundException missing, or not addressed to the Person (404)
     */
    public function execute(int $wordPressUserId, string $invitationId): array
    {
        $recipient = $this->recipients->recipient($wordPressUserId);

        try {
            $id = OrganizationInvitationId::fromString($invitationId);
        } catch (InvalidArgumentException $exception) {
            throw new OrganizationInvitationNotFoundException('Organization invitation not found.');
        }

        $invitation = $this->invitations->findById($id);

        if ($invitation === null || ! $this->recipients->isEmailAddressedTo($recipient, $invitation->email())) {
            throw new OrganizationInvitationNotFoundException('Organization invitation not found.');
        }

        $organization = $this->organizations->findById($invitation->organizationId());

        if ($organization === null) {
            throw new OrganizationInvitationNotFoundException('Organization invitation not found.');
        }

        $membership = $this->memberships->findByOrganizationAndPerson($invitation->organizationId(), $recipient->person()->id());

        return [
            'id' => $invitation->id()->toString(),
            'organization_id' => $invitation->organizationId()->toString(),
            'organization_name' => $organization->name()->toString(),
            'status' => $membership !== null && $membership->isActive()
                ? self::STATUS_REGISTERED
                : self::invitationStatus($invitation->status()->toString(), $invitation->isExpired()),
        ];
    }

    private static function invitationStatus(string $status, bool $expired): string
    {
        if ($status === OrganizationInvitationStatus::PENDING) {
            return $expired ? self::STATUS_EXPIRED : self::STATUS_PENDING;
        }

        return $status === OrganizationInvitationStatus::CANCELLED ? self::STATUS_CANCELLED : self::STATUS_CLOSED;
    }
}
