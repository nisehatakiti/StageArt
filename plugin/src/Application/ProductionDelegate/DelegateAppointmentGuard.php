<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B §4.6 / §7.1 (stage 4): a delegate row can be created, or made
 * ACTIVE again, only for a Person who is an ACTIVE PERSON Participant of
 * the Production (no recorded consent needed since the 2026-11-27
 * invitation policy). Called inside the appointment's
 * transaction after the Production lock: the Person's Participant rows are
 * read with SELECT ... FOR UPDATE (lock order Production -> Participant ->
 * delegate, §5.4), so a concurrent withdrawal cannot slip in between the
 * check and the delegate write. A failed read throws (never read as
 * "eligible" or "not eligible").
 */
final class DelegateAppointmentGuard
{
    private ParticipantRepositoryInterface $participants;

    public function __construct(ParticipantRepositoryInterface $participants)
    {
        $this->participants = $participants;
    }

    /**
     * @throws ProductionDelegateTargetNotParticipantException
     */
    public function assertEligible(ProductionId $productionId, PersonId $personId): void
    {
        self::assertNoReason($this->lockAndCheck($productionId, $personId));
    }

    /**
     * Locks the Person's Participant rows (FOR UPDATE) and returns why they
     * could not be appointed (NOT_ACTIVE_PARTICIPANT),
     * or null - for callers that decide whether the change is an
     * appointment only after locking the delegate row (§5.4 order).
     */
    public function lockAndCheck(ProductionId $productionId, PersonId $personId): ?string
    {
        return ProductionAuthorizationService::participantIneffectiveReason(
            $this->participants->findPersonParticipantsForUpdate($productionId, $personId->toString())
        );
    }

    /**
     * @throws ProductionDelegateTargetNotParticipantException
     */
    public static function assertNoReason(?string $reason): void
    {
        if ($reason === ProductionAuthorizationService::DELEGATE_NOT_ACTIVE_PARTICIPANT) {
            throw new ProductionDelegateTargetNotParticipantException(
                'A delegate must be an ACTIVE member of this Production.'
            );
        }
    }
}
