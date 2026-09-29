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
 * §16/§17: the single entry point behind `POST /productions/{id}/
 * participant-invitations`. Despite the URL's name, this does not
 * always create an invitation - per this round's explicit instruction,
 * an email that already belongs to an existing StageArt Person is added
 * directly as a Participant via the existing, unchanged
 * CreateParticipantUseCase (§17: "既存CreateParticipantUseCaseを利用でき
 * る場合は必ず利用してください"), and Authorization for that path is
 * exactly CreateParticipantUseCase's own, untouched - this Use Case adds
 * no second gate around it.
 *
 * §11's duplicate-invitation rule (an existing usable PENDING invitation
 * for the same Production+email+ParticipantType is resent, not
 * duplicated) is delegated to ResendParticipantInvitationUseCase, so the
 * "rotate token, send mail" logic lives in exactly one place whether it
 * runs from this path or from the explicit `POST .../resend` REST
 * action.
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
