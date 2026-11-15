<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalActivityType;
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
    private RehearsalStatusTimeSync $statusTimeSync;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        RehearsalReminderDispatcher $reminderDispatcher,
        RehearsalReminderSchedulerInterface $reminderScheduler,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions,
        RehearsalStatusTimeSync $statusTimeSync
    ) {
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->reminderDispatcher = $reminderDispatcher;
        $this->reminderScheduler = $reminderScheduler;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->statusTimeSync = $statusTimeSync;
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

        $rehearsal = $this->statusTimeSync->sync($rehearsal);

        // PR #7 R10 (§23.3): read every date/time as an instant in the
        // Rehearsal's timezone. A request without a timezone keeps the
        // Rehearsal's current one (before R10 it cleared it, which changed
        // how the stored wall-clock times were read back).
        $zone = RehearsalDateTime::zone(
            $command->timezone !== null && trim($command->timezone) !== '' ? $command->timezone : $rehearsal->timezone()
        );
        $startDateTime = RehearsalDateTime::parseRequired($command->startDateTime, $zone, 'start date/time');
        $endDateTime = RehearsalDateTime::parseRequired($command->endDateTime, $zone, 'end date/time');
        $newResponseDeadline = RehearsalDateTime::parseOptional($command->responseDeadline, $zone);
        $activityType = $this->parseOptionalActivityType($command->activityType);

        $sendReminderAfterCommit = false;

        $this->transactions->run(function () use ($rehearsal, $command, $newResponseDeadline, $activityType, $zone, $startDateTime, $endDateTime, &$sendReminderAfterCommit): void {
            $sendReminderAfterCommit = false;
            $rehearsal->updateBasicInfo(
                $command->title,
                $command->description,
                $startDateTime,
                $endDateTime,
                $zone->getName(),
                $command->location,
                $activityType
            );

            $oldResponseDeadline = $rehearsal->responseDeadline();
            $rehearsal->changeResponseDeadline($newResponseDeadline);
            $sendReminderAfterCommit = $this->applyReminderPolicy($rehearsal, $oldResponseDeadline, $newResponseDeadline);

            $this->rehearsals->save($rehearsal);
        });

        // PR #7 R10: the immediate reminder (deadline brought forward past
        // its reminder time, §10.2) goes out only after the change commits.
        if ($sendReminderAfterCommit) {
            $this->reminderDispatcher->send($rehearsal);
        }

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
    /**
     * @return bool whether a reminder must be sent once the transaction
     *              has committed (PR #7 R10)
     */
    private function applyReminderPolicy(Rehearsal $rehearsal, ?DateTimeImmutable $oldDeadline, ?DateTimeImmutable $newDeadline): bool
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

                if (! $this->reminderDispatcher->isSendable($rehearsal)) {
                    return false;
                }

                // Recorded with the change; sent after the commit.
                $rehearsal->markReminderSent(new DateTimeImmutable());

                return true;
            case RehearsalReminderPolicy::ACTION_SCHEDULE:
                $rehearsal->clearReminderSentMark();
                $this->reminderScheduler->scheduleReminderAt($rehearsal->id(), $decision['reminderAt']);
                break;
            case RehearsalReminderPolicy::ACTION_CANCEL:
                $this->reminderScheduler->cancelReminder($rehearsal->id());
                break;
        }

        return false;
    }
    /**
     * StageArt 予定・活動一般化調査: null/empty leaves the Rehearsal's
     * current species unchanged (see Rehearsal::updateBasicInfo()'s own
     * docblock) - an invalid species string is rejected as a 422, same
     * as every other invalid Rehearsal input.
     */
    private function parseOptionalActivityType(?string $value): ?RehearsalActivityType
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return RehearsalActivityType::fromString($value);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException("Invalid activity_type value: {$value}");
        }
    }
}
