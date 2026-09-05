<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

/**
 * "稽古詳細画面で未選択メンバーを追加" - lets a Rehearsal manager add
 * RehearsalAttendance targets for Production members who were not
 * selected at creation time (CreateRehearsalUseCase.php) or at confirm
 * time (ConfirmRehearsalUseCase.php's own Phase1-intersection logic).
 *
 * Every requested PersonId must be a currently-ACTIVE, Person-subject
 * Participant of this Rehearsal's Production - the same
 * MembershipContract::activeProductionMemberPersonIds() pool
 * CreateRehearsalUseCase validates against - or the whole call is
 * rejected (InvalidArgumentException), matching CreateRehearsalUseCase's
 * own all-or-nothing validation style rather than silently dropping
 * invalid entries.
 *
 * The target Phase is derived from the Rehearsal's current Status via
 * RehearsalAttendancePhase::forRehearsalStatus() (existing helper,
 * already used identically by Dashboard's UpcomingRehearsal resolution)
 * - not always Phase 1, so a member added after CONFIRMED gets a Phase 2
 * (ATTENDANCE_CONFIRMATION) record directly, matching what a member
 * selected before that transition would already have.
 *
 * Idempotent per Person: someone who already has an Attendance record
 * for this Rehearsal's current Phase is left untouched (skipped, not
 * re-created) - this is what keeps an existing participant from being
 * "re-sent" a confirmation just because the manager saved this screen
 * again; only genuinely new targets get a fresh UNANSWERED record. The
 * return value is therefore only the newly-created records, not the
 * full roster.
 */
final class AddRehearsalAttendanceTargetsUseCase
{
    private RehearsalAttendanceRepositoryInterface $attendances;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private MembershipContract $membership;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        RehearsalAttendanceRepositoryInterface $attendances,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        MembershipContract $membership,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->attendances = $attendances;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->membership = $membership;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    /**
     * @return RehearsalAttendanceResult[] the newly-created records only
     */
    public function execute(AddRehearsalAttendanceTargetsCommand $command): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new RehearsalAttendanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsal = $this->rehearsals->findById(RehearsalId::fromString($command->rehearsalId));

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($command->rehearsalId);
        }

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, RehearsalCapability::MANAGE)) {
            throw new RehearsalAttendanceAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can add Rehearsal Attendance targets.'
            );
        }

        $activeMemberIdStrings = array_map(
            static fn (PersonId $personId): string => $personId->toString(),
            $this->membership->activeProductionMemberPersonIds($productionId)
        );

        foreach ($command->personIds as $personIdString) {
            if (! in_array($personIdString, $activeMemberIdStrings, true)) {
                throw new InvalidArgumentException(
                    "Person {$personIdString} is not an active Participant of this Production and cannot be added as a Rehearsal Attendance target."
                );
            }
        }

        $phase = RehearsalAttendancePhase::forRehearsalStatus($rehearsal->status());

        $created = $this->transactions->run(
            function () use ($rehearsal, $phase, $command): array {
                $createdRecords = [];

                foreach ($command->personIds as $personIdString) {
                    $personId = PersonId::fromString($personIdString);
                    $existing = $this->attendances->findByRehearsalIdAndPersonIdAndPhase($rehearsal->id(), $personId, $phase);

                    if ($existing !== null) {
                        continue;
                    }

                    $attendance = $phase->equals(RehearsalAttendancePhase::scheduleAdjustment())
                        ? RehearsalAttendance::createPhase1($rehearsal->id(), $personId)
                        : RehearsalAttendance::createPhase2($rehearsal->id(), $personId);

                    $this->attendances->save($attendance);
                    $createdRecords[] = $attendance;
                }

                return $createdRecords;
            }
        );

        return array_map(
            static fn (RehearsalAttendance $attendance): RehearsalAttendanceResult => RehearsalAttendanceResult::fromDomain($attendance),
            $created
        );
    }
}
