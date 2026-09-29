<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Application\Participant\CreateParticipantCommand;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use Throwable;

/**
 * §15/§2 (this round's absolute condition): resolves every outstanding
 * ParticipantInvitation for `$email` once `$personId` has just completed
 * their own, ordinary registration. Called internally only - never
 * exposed as a REST endpoint (§15).
 *
 * This is the piece that must never bypass Participant creation
 * Authorization. It does NOT call Participant::create() directly and it
 * does NOT disable/skip ProductionAuthorizationService in any way.
 * Instead it re-invokes the existing, unchanged CreateParticipantUseCase
 * - using the invitation's own `invitedByPersonId` (resolved to that
 * Person's WordPress user id) as `requestedByWordPressUserId`. That
 * Person was a PrimaryManager or PARTICIPANT_MANAGER at the moment they
 * created this invitation, so CreateParticipantUseCase's own
 * canManageParticipants() check is what actually authorizes this
 * Participant's creation here too, exactly as it authorizes every other
 * Participant addition in this codebase - it is simply being asked, on
 * the inviter's behalf, "is this invitation's own creator still allowed
 * to add this Participant?" If that Person has since lost the
 * PARTICIPANT_MANAGER Role (or PrimaryManager status changed hands),
 * CreateParticipantUseCase legitimately refuses, and this invitation is
 * left PENDING/unresolved rather than forcing the addition through.
 *
 * The invitee themselves is never treated as a PrimaryManager/
 * PARTICIPANT_MANAGER at any point in this flow - they are only ever
 * the `subject_id` being added, never the `requestedByWordPressUserId`.
 */
final class ResolveParticipantInvitationUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ParticipantRepositoryInterface $participants;
    private PersonRepositoryInterface $people;
    private CreateParticipantUseCase $createParticipant;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ParticipantRepositoryInterface $participants,
        PersonRepositoryInterface $people,
        CreateParticipantUseCase $createParticipant
    ) {
        $this->invitations = $invitations;
        $this->participants = $participants;
        $this->people = $people;
        $this->createParticipant = $createParticipant;
    }

    public function execute(PersonId $personId, string $email): void
    {
        foreach ($this->invitations->findByEmail($email) as $invitation) {
            if (! $invitation->isUsable()) {
                continue;
            }

            try {
                $this->resolveOne($invitation, $personId);
            } catch (Throwable $exception) {
                // Mirrors WordPressNotificationDispatcher's own
                // catch-and-continue-with-error_log() shape: a single
                // invitation that cannot be resolved (e.g. the inviter
                // has since lost PARTICIPANT_MANAGER) must never break
                // the registration response this runs inside of, and
                // must never stop other outstanding invitations for the
                // same email from resolving.
                error_log(sprintf(
                    '[StageArt ParticipantInvitation] failed to resolve invitation=%s for person=%s email=%s: %s',
                    $invitation->id()->toString(),
                    $personId->toString(),
                    $email,
                    $exception->getMessage()
                ));
            }
        }
    }

    private function resolveOne(ParticipantInvitation $invitation, PersonId $personId): void
    {
        $existingParticipant = $this->participants->findByProductionAndSubject(
            $invitation->productionId(),
            ParticipantSubjectType::person(),
            $personId->toString(),
            $invitation->participantType()
        );

        if ($existingParticipant === null) {
            $inviter = $this->people->findById($invitation->invitedByPersonId());

            if ($inviter === null) {
                // The inviter Person no longer resolves - there is no
                // one whose authorization this invitation can be
                // fulfilled under. Leave PENDING rather than forcing it
                // through some other way.
                return;
            }

            try {
                $this->createParticipant->execute(new CreateParticipantCommand(
                    $invitation->productionId()->toString(),
                    $inviter->wordPressUserId(),
                    ParticipantSubjectType::PERSON,
                    $personId->toString(),
                    $invitation->participantType()->toString(),
                    null,
                    $invitation->remarks()
                ));
            } catch (ParticipantAlreadyExistsException $exception) {
                // §12: a manager already added this same Person directly
                // (e.g. by Person ID) while the invitation was still
                // PENDING - not an error. Fall through to consume()
                // below so the invitation still resolves, without ever
                // creating a second Participant row.
            }
        }

        $invitation->consume();
        $this->invitations->save($invitation);
    }
}
