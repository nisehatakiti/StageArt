<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Join request (Join Key or a published Production), R14-B §5.2 / §5.4 /
 * §7.1 (U13 A+C案, N1):
 *
 * - One transaction: (1) lock the Production, (2) create or reuse the one
 *   Participant row of (production, PERSON, the Person, type) through
 *   ParticipantEnrollmentService - read again FOR UPDATE; CANCELLED /
 *   REJECTED / WITHDRAWN / INACTIVE becomes PENDING on the same row (id
 *   and enrollmentSource kept), a new row is JOIN_REQUEST; ACTIVE /
 *   PENDING / DRAFT are 409 - with requestedAt = this request, (3) for a
 *   Join Key request only, count the use atomically after the row is
 *   saved (a key that can no longer be used: 404 and everything rolled
 *   back), (4) JOIN_REQUESTED. Any failure rolls back both the request and
 *   the use: a use is never counted without a stored request, and no 201
 *   is returned without the row.
 * - The Join Key / Production checks before the transaction stay as they
 *   were (unknown or unusable key, unpublished Production: 404).
 */
final class RequestProductionParticipationUseCase
{
    private ProductionRepositoryInterface $productions;
    private JoinKeyRepositoryInterface $joinKeys;
    private ProductionAuthorizationService $authorization;
    private ParticipantEnrollmentService $enrollment;
    private ParticipantEventRecorder $events;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        JoinKeyRepositoryInterface $joinKeys,
        ProductionAuthorizationService $authorization,
        ParticipantEnrollmentService $enrollment,
        ParticipantEventRecorder $events,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->joinKeys = $joinKeys;
        $this->authorization = $authorization;
        $this->enrollment = $enrollment;
        $this->events = $events;
        $this->transactions = $transactions;
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

        $now = new DateTimeImmutable();

        $participant = $this->transactions->run(function () use ($productionId, $person, $participantType, $joinKey, $command, $now): Participant {
            if ($this->productions->findByIdForUpdate($productionId) === null) {
                throw new ProductionNotFoundException($productionId->toString());
            }

            $outcome = $this->enrollment->requestParticipation($productionId, $person->id(), $participantType, $now);

            if ($joinKey !== null && ! $this->joinKeys->recordUseIfUsable($joinKey->id(), $now)) {
                throw new JoinKeyNotFoundException((string) $command->joinKeyCode);
            }

            $this->events->joinRequested($outcome->participant, $outcome->previousStatus, $now);

            return $outcome->participant;
        });

        return ParticipantRequestResult::fromDomain($participant, $person);
    }
}
