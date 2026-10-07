<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Membership\MembershipAlreadyExistsException;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationMemberResult;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;

/**
 * StageArt 団体管理UI整理 "メンバーを追加": mirrors
 * CreateParticipantInvitationUseCase's own 氏名＋メールアドレス single-
 * entry-point decision exactly - an admin always submits 氏名＋メール
 * アドレス（＋備考）; this Use Case decides internally, by email, whether
 * that becomes an existing Person's ACTIVE Membership (created directly,
 * no approval step - whoever is adding them already holds OWNER/
 * ORGANIZATION_MANAGER authority), a resend of an already-usable PENDING
 * invitation for the same (organization, email), or a brand-new
 * OrganizationInvitation.
 *
 * Unlike Participant (which has its own independent `displayName`), a
 * Membership carries no name field of its own - the Organization roster
 * always displays the Person's own familyName/givenName (see
 * OrganizationMemberResult). The submitted `name` is therefore only
 * meaningful for the not-yet-registered-email branch (the eventual
 * registration screen's initial value, via `invitedName`) and is
 * otherwise discarded for an existing Person.
 */
final class CreateOrganizationInvitationUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private OrganizationAuthorizationService $authorization;
    private FindPersonByEmailUseCase $findPersonByEmail;
    private MembershipRepositoryInterface $memberships;
    private OrganizationInvitationRepositoryInterface $invitations;
    private ResendOrganizationInvitationUseCase $resendOrganizationInvitation;
    private OrganizationInvitationMailerInterface $mailer;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        OrganizationAuthorizationService $authorization,
        FindPersonByEmailUseCase $findPersonByEmail,
        MembershipRepositoryInterface $memberships,
        OrganizationInvitationRepositoryInterface $invitations,
        ResendOrganizationInvitationUseCase $resendOrganizationInvitation,
        OrganizationInvitationMailerInterface $mailer
    ) {
        $this->organizations = $organizations;
        $this->authorization = $authorization;
        $this->findPersonByEmail = $findPersonByEmail;
        $this->memberships = $memberships;
        $this->invitations = $invitations;
        $this->resendOrganizationInvitation = $resendOrganizationInvitation;
        $this->mailer = $mailer;
    }

    public function execute(CreateOrganizationInvitationCommand $command): CreateOrganizationInvitationResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);
        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        if (! $this->authorization->hasRole($requester, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can add a member.');
        }

        $email = trim($command->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address: {$command->email}");
        }

        $name = trim($command->name);

        if ($name === '') {
            throw new InvalidArgumentException('name must not be empty.');
        }

        $existingPerson = $this->findPersonByEmail->execute($email);

        if ($existingPerson !== null) {
            $targetPersonId = PersonId::fromString($existingPerson->id);
            $existingMembership = $this->memberships->findByOrganizationAndPerson($organizationId, $targetPersonId);

            if ($existingMembership !== null && $existingMembership->isActive()) {
                throw new MembershipAlreadyExistsException();
            }

            $membership = Membership::create($organizationId, $targetPersonId, RoleKey::member());
            $this->memberships->save($membership);

            return CreateOrganizationInvitationResult::forExistingPerson(
                OrganizationMemberResult::fromDomain($membership, null)
            );
        }

        foreach ($this->invitations->findByOrganizationAndEmail($organizationId, $email) as $candidate) {
            if ($candidate->isUsable()) {
                $resent = $this->resendOrganizationInvitation->execute(new ResendOrganizationInvitationCommand(
                    $candidate->id()->toString(),
                    $command->requestedByWordPressUserId
                ));

                return CreateOrganizationInvitationResult::forResentInvitation($resent);
            }
        }

        $tokenValue = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenValue);
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval(OrganizationInvitation::TOKEN_LIFETIME_SPEC));

        $invitation = OrganizationInvitation::create(
            $organizationId,
            $email,
            $name,
            $requester->id(),
            $command->remarks,
            $tokenHash,
            $expiresAt
        );
        $this->invitations->save($invitation);

        $this->mailer->sendInvitationEmail($email, $organization->name()->toString(), $tokenValue);

        return CreateOrganizationInvitationResult::forNewInvitation(OrganizationInvitationResult::fromDomain($invitation));
    }
}
