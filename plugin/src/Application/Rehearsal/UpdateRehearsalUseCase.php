<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;

/**
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class UpdateRehearsalUseCase
{
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private RehearsalReminderDispatcher $reminderDispatcher;
    private RehearsalReminderSchedulerInterface $reminderScheduler;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        RehearsalReminderDispatcher $reminderDispatcher,
        RehearsalReminderSchedulerInterface $reminderScheduler,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->reminderDispatcher = $reminderDispatcher;
        $this->reminderScheduler = $reminderScheduler;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(UpdateRehearsalCommand $command): RehearsalResult
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
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can update this Rehearsal.'
            );
        }

        $newResponseDeadline = $this->parseOptionalDateTime($command->responseDeadline);

        $this->transactions->run(function () use ($rehearsal, $command, $newResponseDeadline): void {
            $rehearsal->updateBasicInfo(
                $command->title,
                $command->description,
                $this->parseOptionalDateTime($command->startDateTime),
                $this->parseOptionalDateTime($command->endDateTime),
                $command->timezone,
                $command->location
            );

            $oldResponseDeadline = $rehearsal->responseDeadline();
            $rehearsal->changeResponseDeadline($newResponseDeadline);
            $this->applyReminderPolicy($rehearsal, $oldResponseDeadline, $newResponseDeadline);

            $this->rehearsals->save($rehearsal);
        });

        return RehearsalResult::fromDomain($rehearsal);
    }

    /**
     * Phase 7: applies `RehearsalReminderPolicy`'s decision for this
     * deadline change - resets the duplicate-send guard only on an
     * actual reschedule (SCHEDULE/SEND_NOW), never on ACTION_NONE (an
     * extension must leave an already-sent Reminder's history alone, per
     * the confirmed spec) or ACTION_CANCEL (nothing to guard once there
     * is no deadline at all).
     */
    private function applyReminderPolicy(Rehearsal $rehearsal, ?DateTimeImmutable $oldDeadline, ?DateTimeImmutable $newDeadline): void
    {
        $decision = RehearsalReminderPolicy::decideOnDeadlineChange($oldDeadline, $newDeadline, new DateTimeImmutable());

        switch ($decision['action']) {
            case RehearsalReminderPolicy::ACTION_SEND_NOW:
                // Backend PHPUnit環境整備 Phase: a prior deadline change
                // on this same Rehearsal may already have scheduled a
                // Reminder for a later time (ACTION_SCHEDULE below) -
                // sending now instead must cancel that stale schedule,
                // or the Scheduler would still fire it again later,
                // producing a duplicate send. CreateRehearsalUseCase's
                // own identical branch does not need this: `$oldDeadline`
                // is always null there, so no prior schedule can exist
                // to leak.
                $this->reminderScheduler->cancelReminder($rehearsal->id());
                $rehearsal->clearReminderSentMark();
                $this->reminderDispatcher->dispatch($rehearsal);
                break;
            case RehearsalReminderPolicy::ACTION_SCHEDULE:
                $rehearsal->clearReminderSentMark();
                $this->reminderScheduler->scheduleReminderAt($rehearsal->id(), $decision['reminderAt']);
                break;
            case RehearsalReminderPolicy::ACTION_CANCEL:
                $this->reminderScheduler->cancelReminder($rehearsal->id());
                break;
        }
    }

    private function parseOptionalDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid date/time value: {$value}");
        }
    }
}
