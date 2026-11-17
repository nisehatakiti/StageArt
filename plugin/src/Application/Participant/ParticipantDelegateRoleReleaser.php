<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;

/**
 * R14-B §4.6 / §5.2 (D9, U15): the one place that deactivates a Person's
 * ProductionDelegate rows in a Production when their Participant state
 * changes. Every ACTIVE row of that Person in that Production becomes
 * INACTIVE (never deleted; updated_by / updated_at set to the actor and
 * now), each with a DELEGATE_DEACTIVATED audit event carrying the reason.
 * An INACTIVE row is never turned back to ACTIVE here.
 *
 * Reasons:
 * - PARTICIPANT_WITHDRAWN / PARTICIPANT_REMOVED / PARTICIPANT_DEACTIVATED:
 *   the Participant stops being ACTIVE.
 * - DORMANT_ON_ENROLLMENT: the Participant becomes ACTIVE from a non-ACTIVE
 *   state (or from no row), so an old appointment left ACTIVE while they
 *   were not a member does not silently come back into force. The caller
 *   does NOT call this when an already ACTIVE Participant only records
 *   consent (U15: their ACTIVE rows stay and become effective).
 *
 * Called inside the business transaction, after the Participant row is
 * locked (lock order Production -> invitation -> Participant -> delegate).
 */
final class ParticipantDelegateRoleReleaser
{
    public const PARTICIPANT_WITHDRAWN = 'PARTICIPANT_WITHDRAWN';
    public const PARTICIPANT_REMOVED = 'PARTICIPANT_REMOVED';
    public const PARTICIPANT_DEACTIVATED = 'PARTICIPANT_DEACTIVATED';
    public const DORMANT_ON_ENROLLMENT = 'DORMANT_ON_ENROLLMENT';

    private const REASONS = [
        self::PARTICIPANT_WITHDRAWN,
        self::PARTICIPANT_REMOVED,
        self::PARTICIPANT_DEACTIVATED,
        self::DORMANT_ON_ENROLLMENT,
    ];

    private ProductionDelegateRepositoryInterface $delegates;
    private ParticipantEventRecorder $events;

    public function __construct(ProductionDelegateRepositoryInterface $delegates, ParticipantEventRecorder $events)
    {
        $this->delegates = $delegates;
        $this->events = $events;
    }

    /**
     * @return int how many rows were deactivated
     */
    public function release(
        ProductionId $productionId,
        PersonId $personId,
        PersonId $actorPersonId,
        string $reason,
        ?Participant $participant,
        DateTimeImmutable $at
    ): int {
        if (! in_array($reason, self::REASONS, true)) {
            throw new InvalidArgumentException("Invalid delegate release reason: {$reason}");
        }

        $released = 0;

        foreach ($this->delegates->findByProductionAndPersonForUpdate($productionId, $personId) as $delegate) {
            if (! $delegate->isActive()) {
                continue;
            }

            $delegate->deactivate($actorPersonId);
            $this->delegates->save($delegate);
            $this->events->delegateDeactivated($delegate, $participant, $actorPersonId, $reason, $at);
            $released++;
        }

        return $released;
    }
}
