<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

/**
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 *
 * Phase 7 (Rehearsal仕様整合) §8: Cancel now notifies every participant
 * who was ever an Attendance target for this Rehearsal (the union of
 * both Phase 1/2 target lists - a Rehearsal already CONFIRMED into
 * Phase 2 may still have people who never got a Phase 2 record added,
 * and Phase 1 targets remain relevant history either way), and cancels
 * any pending Reminder for it. Wrapped in a Transaction Boundary for the
 * first time (previously `cancel()` + `save()` with no boundary at all)
 * to match `PublishTimetableVersionUseCase`'s established "state change
 * + notify, one atomic unit, notify synchronously from inside" pattern -
 * see that class's own docblock for why this codebase does not use an
 * outbox/queue here.
 */
final class CancelRehearsalUseCase
{
    private RehearsalRepositoryInterface $rehearsals;
    private RehearsalAttendanceRepositoryInterface $attendances;
    private ProductionContextContract $productionContext;
    private NotificationContract $notificationContract;
    private RehearsalReminderSchedulerInterface $reminderScheduler;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        RehearsalAttendanceRepositoryInterface $attendances,
        ProductionContextContract $productionContext,
        NotificationContract $notificationContract,
        RehearsalReminderSchedulerInterface $reminderScheduler,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->rehearsals = $rehearsals;
        $this->attendances = $attendances;
        $this->productionContext = $productionContext;
        $this->notificationContract = $notificationContract;
        $this->reminderScheduler = $reminderScheduler;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(CancelRehearsalCommand $command): RehearsalResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new RehearsalAccessDeniedException('No StageArt Person is linked to this WordPress user.');
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
            throw new RehearsalAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can cancel this Rehearsal.'
            );
        }

        $this->transactions->run(function () use ($rehearsal, $production): void {
            $rehearsal->cancel();
            $this->rehearsals->save($rehearsal);

            $this->reminderScheduler->cancelReminder($rehearsal->id());

            if ($rehearsal->startDateTime() !== null) {
                $message = RehearsalNotificationMessageBuilder::buildCancelledMessage($production->name, $rehearsal->startDateTime());

                $targetPersonIds = [];

                foreach ([RehearsalAttendancePhase::scheduleAdjustment(), RehearsalAttendancePhase::attendanceConfirmation()] as $phase) {
                    foreach ($this->attendances->findByRehearsalIdAndPhase($rehearsal->id(), $phase) as $attendance) {
                        $targetPersonIds[$attendance->personId()->toString()] = $attendance->personId();
                    }
                }

                foreach ($targetPersonIds as $personId) {
                    $this->notificationContract->notify($personId, 'rehearsal_cancelled', [
                        'rehearsal_id' => $rehearsal->id()->toString(),
                        'production_id' => $rehearsal->productionId()->toString(),
                        'message' => $message,
                    ]);
                }
            }
        });

        return RehearsalResult::fromDomain($rehearsal);
    }
}
