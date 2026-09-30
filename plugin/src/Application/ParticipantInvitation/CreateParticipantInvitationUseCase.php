<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Participant\CreateParticipantCommand;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * StageArt Productionメンバー追加(氏名＋メールアドレス統一)ラウンド: the
 * single entry point behind `POST /productions/{id}/participant-
 * invitations`, now the ONLY member-add path this round's confirmed
 * spec allows (§0/§1 - no separate Person ID input, no standalone
 * "search by email" step, no NAME_ONLY-only add). An admin always
 * submits 氏名＋メールアドレス＋役割＋備考 in one call; this Use Case decides
 * internally, by email, whether that becomes:
 *
 * - an existing Person added directly as a Participant (§2-A), via the
 *   existing, unchanged CreateParticipantUseCase (§17: "既存
 *   CreateParticipantUseCaseを利用できる場合は必ず利用してください") -
 *   Authorization for that path is exactly CreateParticipantUseCase's
 *   own, untouched; this Use Case adds no second gate around it, and
 *   the submitted `name` is never used/stored in this branch (the
 *   existing Person's own real name is what StageArt already displays -
 *   see ParticipantResult's person_family_name/person_given_name
 *   resolution);
 * - a resend of an existing usable PENDING ParticipantInvitation for the
 *   same (production, email, participantType) tuple (§9/§11's
 *   duplicate-invitation rule), delegated to
 *   ResendParticipantInvitationUseCase so "rotate token, send mail"
 *   lives in exactly one place. §9's open question - whether a resend
 *   should also overwrite the stored invitedName/remarks with this
 *   call's newly-submitted values - is deliberately NOT resolved here;
 *   ResendParticipantInvitationUseCase's own signature is unchanged
 *   (no name/remarks parameters), so a resend always keeps the
 *   ORIGINAL invitedName/remarks from when the invitation was first
 *   created. See this round's final report for why this was flagged
 *   rather than decided;
 * - a brand-new ParticipantInvitation (§2-B), carrying the submitted
 *   `name` as `invitedName` (§3) for the eventual registration screen's
 *   initial value.
 */
final class CreateParticipantInvitationUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private FindPersonByEmailUseCase $findPersonByEmail;
    private CreateParticipantUseCase $createParticipant;
    private ParticipantInvitationRepositoryInterface $invitations;
    private ResendParticipantInvitationUseCase $resendParticipantInvitation;
    private ParticipantInvitationMailerInterface $mailer;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        FindPersonByEmailUseCase $findPersonByEmail,
        CreateParticipantUseCase $createParticipant,
        ParticipantInvitationRepositoryInterface $invitations,
        ResendParticipantInvitationUseCase $resendParticipantInvitation,
        ParticipantInvitationMailerInterface $mailer
    ) {
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->findPersonByEmail = $findPersonByEmail;
        $this->createParticipant = $createParticipant;
        $this->invitations = $invitations;
        $this->resendParticipantInvitation = $resendParticipantInvitation;
        $this->mailer = $mailer;
    }

    public function execute(CreateParticipantInvitationCommand $command): CreateParticipantInvitationResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can invite a member.'
            );
        }

        $email = trim($command->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address: {$command->email}");
        }

        $name = trim($command->name);

        if ($name === '') {
            throw new InvalidArgumentException('name must not be empty.');
        }

        $participantType = ParticipantType::fromString($command->participantType);

        $existingPerson = $this->findPersonByEmail->execute($email);

        if ($existingPerson !== null) {
            $participantResult = $this->createParticipant->execute(new CreateParticipantCommand(
                $command->productionId,
                $command->requestedByWordPressUserId,
                ParticipantSubjectType::PERSON,
                $existingPerson->id,
                $command->participantType,
                null,
                $command->remarks
            ));

            return CreateParticipantInvitationResult::forExistingPerson($participantResult);
        }

        foreach ($this->invitations->findByProductionEmailAndType($production->id(), $email, $participantType) as $candidate) {
            if ($candidate->isUsable()) {
                $resent = $this->resendParticipantInvitation->execute(new ResendParticipantInvitationCommand(
                    $candidate->id()->toString(),
                    $command->requestedByWordPressUserId
                ));

                return CreateParticipantInvitationResult::forResentInvitation($resent);
            }
        }

        $tokenValue = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenValue);
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval(ParticipantInvitation::TOKEN_LIFETIME_SPEC));

        $invitation = ParticipantInvitation::create(
            $production->id(),
            $email,
            $name,
            $requester->id(),
            $participantType,
            $command->remarks,
            $tokenHash,
            $expiresAt
        );
        $this->invitations->save($invitation);

        $this->mailer->sendInvitationEmail($email, $production->name()->toString(), $tokenValue);

        return CreateParticipantInvitationResult::forNewInvitation(ParticipantInvitationResult::fromDomain($invitation));
    }
}
