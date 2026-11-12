<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;

final class InMemoryOrganizationInvitationRepository implements OrganizationInvitationRepositoryInterface
{
    /** @var array<string, OrganizationInvitation> */
    private array $invitations = [];

    public function save(OrganizationInvitation $invitation): void
    {
        $this->invitations[$invitation->id()->toString()] = $invitation;
    }

    public function findById(OrganizationInvitationId $id): ?OrganizationInvitation
    {
        return $this->invitations[$id->toString()] ?? null;
    }

    public function findByTokenHash(string $tokenHash): ?OrganizationInvitation
    {
        foreach ($this->invitations as $invitation) {
            if ($invitation->tokenHash() === $tokenHash) {
                return $invitation;
            }
        }

        return null;
    }

    public function findByOrganizationAndEmail(OrganizationId $organizationId, string $email): array
    {
        return array_values(array_filter(
            $this->invitations,
            static fn (OrganizationInvitation $invitation): bool =>
                $invitation->organizationId()->equals($organizationId) && $invitation->email() === $email
        ));
    }

    public function findByEmail(string $email): array
    {
        return array_values(array_filter(
            $this->invitations,
            static fn (OrganizationInvitation $invitation): bool => $invitation->email() === $email
        ));
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        return array_values(array_filter(
            $this->invitations,
            static fn (OrganizationInvitation $invitation): bool => $invitation->organizationId()->equals($organizationId)
        ));
    }
}
