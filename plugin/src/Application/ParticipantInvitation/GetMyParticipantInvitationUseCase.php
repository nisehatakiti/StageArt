<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27):
 * `GET /me/participant-invitations/{id}` - the screen the invitation mail's
 * link opens (/participant-invitations/{id}), after login. The id alone
 * proves nothing: the invitation is shown only when its address is one of
 * the logged-in Person's verified addresses and resolves to them
 * (InvitationRecipientVerifier); otherwise, or when it does not exist, the
 * same 404. A read: nothing is written, nobody is registered or notified.
 *
 * status:
 * - REGISTERED: the Person is an ACTIVE participant of the invitation's type
 *   in the Production (whatever the invitation's own state);
 * - PENDING: still waiting (registered once the invited address is verified);
 * - EXPIRED / CANCELLED: past its expiry / cancelled or closed;
 * - CLOSED: any other end (completed or answered earlier, but not an ACTIVE
 *   participant now).
 */
final class GetMyParticipantInvitationUseCase
{
    public const STATUS_REGISTERED = 'REGISTERED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_CLOSED = 'CLOSED';

    private InvitationRecipientVerifier $recipients;
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private ParticipantRepositoryInterface $participants;

    public function __construct(
        InvitationRecipientVerifier $recipients,
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        ParticipantRepositoryInterface $participants
    ) {
        $this->recipients = $recipients;
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->participants = $participants;
    }

    /**
     * @return array{id: string, production_id: string, production_name: string, participant_type: string, status: string}
     *
     * @throws InvitationRecipientNotEligibleException no Person / not an ACTIVE account (403)
     * @throws ParticipantInvitationNotFoundException missing, or not addressed to the Person (404)
     */
    public function execute(int $wordPressUserId, string $invitationId): array
    {
        $recipient = $this->recipients->recipient($wordPressUserId);

        try {
            $id = ParticipantInvitationId::fromString($invitationId);
        } catch (InvalidArgumentException $exception) {
            throw new ParticipantInvitationNotFoundException('Participant invitation not found.');
        }

        $invitation = $this->invitations->findById($id);

        if ($invitation === null || ! $this->recipients->isAddressedTo($recipient, $invitation)) {
            throw new ParticipantInvitationNotFoundException('Participant invitation not found.');
        }

        $production = $this->productions->findById($invitation->productionId());

        if ($production === null) {
            throw new ParticipantInvitationNotFoundException('Participant invitation not found.');
        }

        $row = $this->participants->findByProductionAndSubject(
            $production->id(),
            ParticipantSubjectType::person(),
            $recipient->person()->id()->toString(),
            $invitation->participantType()
        );

        return [
            'id' => $invitation->id()->toString(),
            'production_id' => $production->id()->toString(),
            'production_name' => $production->name()->toString(),
            'participant_type' => $invitation->participantType()->toString(),
            'status' => $row !== null && $row->isActive()
                ? self::STATUS_REGISTERED
                : self::invitationStatus($invitation->status()->toString(), $invitation->isExpiredAt(new DateTimeImmutable())),
        ];
    }

    private static function invitationStatus(string $status, bool $expired): string
    {
        if ($status === ParticipantInvitationStatus::PENDING) {
            return $expired ? self::STATUS_EXPIRED : self::STATUS_PENDING;
        }

        return $status === ParticipantInvitationStatus::CANCELLED ? self::STATUS_CANCELLED : self::STATUS_CLOSED;
    }
}
