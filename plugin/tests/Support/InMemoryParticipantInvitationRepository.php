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

    /** R14-B stage 3: revocation_notified_at per invitation id (written only by markRevocationNotificationAttempted()). */
    private array $revocationNotifiedAt = [];

    public ?\Throwable $failNextSave = null;

    public ?LockLog $lockLog = null;

    /** @var string[][] the address lists findByVerifiedEmailCandidates() was asked for */
    public array $candidateSearches = [];

    /** How many times findByTokenHash() was called (the R14-B flows never call it). */
    public int $tokenHashLookups = 0;

    /** How many times save() / markRevocationNotificationAttempted() were called (a read calls neither). */
    public int $saves = 0;
    public int $revocationMarks = 0;

    public function save(ParticipantInvitation $invitation): void
    {
        $this->saves++;

        if ($this->failNextSave !== null) {
            $failure = $this->failNextSave;
            $this->failNextSave = null;

            throw $failure;
        }

        $this->invitations[$invitation->id()->toString()] = $invitation;
    }

    public function findByIdForUpdate(ParticipantInvitationId $id): ?ParticipantInvitation
    {
        if ($this->lockLog !== null) {
            $this->lockLog->record('invitation');
        }

        return $this->findById($id);
    }

    public function findByVerifiedEmailCandidates(array $emails): array
    {
        $this->candidateSearches[] = $emails;
        $wanted = array_map(
            static fn (string $email): string => \StageArt\Domain\ParticipantInvitation\InvitationEmailAddress::normalize($email),
            $emails
        );

        return array_values(array_filter(
            $this->invitations,
            static fn (ParticipantInvitation $invitation): bool => in_array(
                \StageArt\Domain\ParticipantInvitation\InvitationEmailAddress::normalize($invitation->email()),
                $wanted,
                true
            )
        ));
    }

    public function markRevocationNotificationAttempted(ParticipantInvitationId $id, \DateTimeImmutable $at): bool
    {
        $this->revocationMarks++;

        if (isset($this->revocationNotifiedAt[$id->toString()])) {
            return false;
        }

        $this->revocationNotifiedAt[$id->toString()] = $at;

        return true;
    }

    public function revocationNotifiedAt(ParticipantInvitationId $id): ?\DateTimeImmutable
    {
        return $this->revocationNotifiedAt[$id->toString()] ?? null;
    }

    /**
     * @return array{0: array<string, ParticipantInvitation>, 1: array<string, \DateTimeImmutable>}
     */
    public function snapshot(): array
    {
        return [
            array_map(static fn (ParticipantInvitation $invitation): ParticipantInvitation => clone $invitation, $this->invitations),
            $this->revocationNotifiedAt,
        ];
    }

    /**
     * @param array{0: array<string, ParticipantInvitation>, 1: array<string, \DateTimeImmutable>} $snapshot
     */
    public function restore(array $snapshot): void
    {
        [$this->invitations, $this->revocationNotifiedAt] = $snapshot;
    }

    public function findById(ParticipantInvitationId $id): ?ParticipantInvitation
    {
        return $this->invitations[$id->toString()] ?? null;
    }

    public function findByTokenHash(string $tokenHash): ?ParticipantInvitation
    {
        $this->tokenHashLookups++;

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
