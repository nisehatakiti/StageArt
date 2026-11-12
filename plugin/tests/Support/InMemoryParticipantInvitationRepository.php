<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

final class InMemoryParticipantInvitationRepository implements ParticipantInvitationRepositoryInterface
{
    /** @var array<string, ParticipantInvitation> */
    private array $invitations = [];

    public function save(ParticipantInvitation $invitation): void
    {
        $this->invitations[$invitation->id()->toString()] = $invitation;
    }

    public function findById(ParticipantInvitationId $id): ?ParticipantInvitation
    {
        return $this->invitations[$id->toString()] ?? null;
    }

    public function findByTokenHash(string $tokenHash): ?ParticipantInvitation
    {
        foreach ($this->invitations as $invitation) {
            if ($invitation->tokenHash() === $tokenHash) {
                return $invitation;
            }
        }

        return null;
    }

    public function findByProductionEmailAndType(
        ProductionId $productionId,
        string $email,
        ParticipantType $participantType
    ): array {
        return array_values(array_filter(
            $this->invitations,
            static fn (ParticipantInvitation $invitation): bool =>
                $invitation->productionId()->equals($productionId)
                && $invitation->email() === $email
                && $invitation->participantType()->equals($participantType)
        ));
    }

    public function findByEmail(string $email): array
    {
        return array_values(array_filter(
            $this->invitations,
            static fn (ParticipantInvitation $invitation): bool => $invitation->email() === $email
        ));
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->invitations,
            static fn (ParticipantInvitation $invitation): bool => $invitation->productionId()->equals($productionId)
        ));
    }
}
