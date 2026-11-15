<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;

final class CreateParticipantUseCase
{
    private ProductionRepositoryInterface $productions;
    private ParticipantRepositoryInterface $participants;
    private PersonRepositoryInterface $people;
    private OrganizationRepositoryInterface $organizations;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;
    private PersonEmailResolver $personEmailResolver;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ParticipantRepositoryInterface $participants,
        PersonRepositoryInterface $people,
        OrganizationRepositoryInterface $organizations,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions,
        PersonEmailResolver $personEmailResolver
    ) {
        $this->productions = $productions;
        $this->participants = $participants;
        $this->people = $people;
        $this->organizations = $organizations;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->personEmailResolver = $personEmailResolver;
    }

    public function execute(CreateParticipantCommand $command): ParticipantResult
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
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can manage Participants.'
            );
        }

        $subjectType = ParticipantSubjectType::fromString($command->subjectType);
        $participantType = ParticipantType::fromString($command->participantType);

        if ($subjectType->equals(ParticipantSubjectType::nameOnly())) {
            if ($command->displayName === null || trim($command->displayName) === '') {
                throw new ParticipantSubjectNotEligibleException('displayName is required for a NAME_ONLY Participant.');
            }

            $participant = $this->transactions->run(
                function () use ($production, $command, $participantType): Participant {
                    $participant = Participant::createNameOnly($production->id(), $command->displayName, $participantType, $command->remarks);
                    $this->participants->save($participant);

                    return $participant;
                }
            );

            return ParticipantResult::fromDomain($participant);
        }

        if ($command->subjectId === null || $command->subjectId === '') {
            throw new ParticipantSubjectNotEligibleException('subjectId is required for a PERSON/ORGANIZATION Participant.');
        }

        $this->assertSubjectExists($subjectType, $command->subjectId);

        if ($this->participants->findByProductionAndSubject(
            $production->id(),
            $subjectType,
            $command->subjectId,
            $participantType
        )) {
            throw new ParticipantAlreadyExistsException(
                'A Participant with this ParticipantType already exists for this Subject on this Production.'
            );
        }

        // StageArt Production側氏名の権威付けラウンド (§0/§2): `displayName`
        // is this Production's own record of the member's name, entirely
        // independent of `subjectType` - a PERSON Participant is free to
        // carry a Production-specific name (e.g. a troupe-qualified
        // "佐藤一郎（劇団いるか）") distinct from that Person's own
        // familyName/givenName. This Use Case never substitutes the
        // Person's own name here or anywhere else in this flow - see
        // ParticipantResult::fromDomain(), which returns both
        // independently rather than resolving one from the other.
        $participant = $this->transactions->run(
            function () use ($production, $subjectType, $command, $participantType): Participant {
                $participant = Participant::create(
                    $production->id(),
                    $subjectType,
                    $command->subjectId,
                    $participantType,
                    $command->remarks,
                    $command->displayName
                );
                $this->participants->save($participant);

                return $participant;
            }
        );

        $isPersonSubject = $subjectType->equals(ParticipantSubjectType::person());
        $person = $isPersonSubject ? $this->people->findById(PersonId::fromString($command->subjectId)) : null;
        $email = $isPersonSubject ? $this->personEmailResolver->resolve(PersonId::fromString($command->subjectId)) : null;

        return ParticipantResult::fromDomain($participant, $person, $email);
    }

    private function assertSubjectExists(ParticipantSubjectType $subjectType, string $subjectId): void
    {
        if ($subjectType->equals(ParticipantSubjectType::person())) {
            if (! $this->people->findById(PersonId::fromString($subjectId))) {
                throw new ParticipantSubjectNotEligibleException('The Subject Person does not exist.');
            }

            return;
        }

        if (! $this->organizations->findById(OrganizationId::fromString($subjectId))) {
            throw new ParticipantSubjectNotEligibleException('The Subject Organization does not exist.');
        }
    }
}
