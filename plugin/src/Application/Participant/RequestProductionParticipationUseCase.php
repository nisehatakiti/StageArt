<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use InvalidArgumentException;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class RequestProductionParticipationUseCase
{
    private ProductionRepositoryInterface $productions;
    private ParticipantRepositoryInterface $participants;
    private JoinKeyRepositoryInterface $joinKeys;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ParticipantRepositoryInterface $participants,
        JoinKeyRepositoryInterface $joinKeys,
        ProductionAuthorizationService $authorization
    ) {
        $this->productions = $productions;
        $this->participants = $participants;
        $this->joinKeys = $joinKeys;
        $this->authorization = $authorization;
    }

    public function execute(RequestProductionParticipationCommand $command): ParticipantRequestResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $participantType = ParticipantType::fromString($command->participantType);
        $joinKey = null;

        if ($command->joinKeyCode !== null) {
            $joinKey = $this->joinKeys->findByCode(JoinKey::normalizeCode($command->joinKeyCode));

            if (! $joinKey || ! $joinKey->isUsable() || $joinKey->targetType() !== JoinKey::TARGET_TYPE_PRODUCTION) {
                throw new JoinKeyNotFoundException($command->joinKeyCode);
            }

            $productionId = ProductionId::fromString($joinKey->targetId());
            $production = $this->productions->findById($productionId);

            if (! $production) {
                throw new ProductionNotFoundException($joinKey->targetId());
            }
        } elseif ($command->productionId !== null) {
            $productionId = ProductionId::fromString($command->productionId);
            $production = $this->productions->findById($productionId);

            if (! $production || ! $production->isPublished()) {
                throw new ProductionNotFoundException($command->productionId);
            }
        } else {
            throw new InvalidArgumentException('Either productionId or joinKeyCode is required.');
        }

        $existing = $this->participants->findByProductionAndSubject(
            $productionId,
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            $participantType
        );

        if ($existing && in_array($existing->status()->toString(), [ParticipantStatus::ACTIVE, ParticipantStatus::PENDING], true)) {
            throw new ParticipantAlreadyExistsException(
                "Person {$person->id()->toString()} already has a {$existing->status()->toString()} Participant request for Production {$productionId->toString()}."
            );
        }

        $participant = Participant::requestParticipation($productionId, ParticipantSubjectType::person(), $person->id()->toString(), $participantType);
        $this->participants->save($participant);

        if ($joinKey !== null) {
            $joinKey->recordUse();
            $this->joinKeys->save($joinKey);
        }

        return ParticipantRequestResult::fromDomain($participant, $person);
    }
}
