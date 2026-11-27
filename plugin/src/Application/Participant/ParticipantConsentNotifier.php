<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use Throwable;

/**
 * R14-B §11.1 / §11.2: the in-app / e-mail notifications of the invitee's
 * answers and of a Participant leaving, through the existing
 * NotificationDispatcherInterface.
 *
 * - Callers invoke these only after their transaction has committed, so a
 *   rolled-back change is never announced.
 * - A failure here never undoes the committed change nor turns the
 *   response into an error: it is caught and logged with the notification
 *   type and row ids only - never an e-mail address, a name or a token.
 * - The name in an invitation message is the one the manager typed when
 *   inviting (invited_name), never the invitee's own Person name or
 *   address (not shared with managers before acceptance).
 * - Creating / resending an invitation sends no in-app notification (§9.2);
 *   a PRODUCTION_CLOSED invalidation is not notified (§11.1).
 */
final class ParticipantConsentNotifier
{
    public const TYPE_INVITATION_ACCEPTED = 'participant_invitation_accepted';
    public const TYPE_INVITATION_REJECTED = 'participant_invitation_rejected';
    public const TYPE_INVITATION_CLOSED = 'participant_invitation_closed';
    public const TYPE_PARTICIPANT_WITHDRAWN = 'participant_withdrawn';
    public const TARGET_TYPE = 'production';

    private NotificationDispatcherInterface $dispatcher;

    public function __construct(NotificationDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * §11.5: the one INVITER_REVOKED notification attempt, to the
     * PrimaryManager - detected when an invitation would register the
     * invited Person (ResolveParticipantInvitationUseCase; the acceptance
     * before the 2026-11-27 invitation policy). The acceptance / rejection
     * notifications to the inviter were removed with the acceptance and
     * rejection; their types stay for the notifications already sent.
     */
    public function invitationClosedByRevokedInviter(ParticipantInvitation $invitation, Production $production): void
    {
        $this->send(
            $production->primaryManagerPersonId(),
            self::TYPE_INVITATION_CLOSED,
            $production,
            sprintf(
                '%sさん宛ての招待は、招待者の権限がなくなったため無効になりました。必要なら改めて招待してください。',
                $invitation->invitedName()
            ),
            $invitation->id()->toString()
        );
    }

    /**
     * §7.2 withdraw: to the PrimaryManager and the effective
     * PARTICIPANT_MANAGERs (`$recipients`, decided by the caller). Each
     * recipient's failure is contained separately.
     *
     * @param PersonId[] $recipients
     */
    public function participantWithdrawn(Production $production, string $memberName, array $recipients, string $participantId): void
    {
        $message = sprintf('%sさんが『%s』の参加をやめました。', $memberName, $production->name()->toString());

        foreach ($recipients as $recipient) {
            $this->send($recipient, self::TYPE_PARTICIPANT_WITHDRAWN, $production, $message, $participantId);
        }
    }

    private function send(PersonId $recipient, string $type, Production $production, string $message, string $rowId): void
    {
        try {
            $this->dispatcher->dispatch($recipient, $type, [
                'production_id' => $production->id()->toString(),
                'target_type' => self::TARGET_TYPE,
                'target_id' => $production->id()->toString(),
                'message' => $message,
            ]);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[StageArt participant notification] %s for %s (production %s) not sent: %s',
                $type,
                $rowId,
                $production->id()->toString(),
                get_class($exception)
            ));
        }
    }
}
