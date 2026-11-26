<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use DateTimeImmutable;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * `PUT /production-delegates/{id}` (Role / status of one delegate row).
 *
 * R14-B §4.6 / §7.1 (stage 4): when the row ends up ACTIVE as a new
 * appointment - made ACTIVE from INACTIVE, or given another Role - its
 * Person must be an ACTIVE PERSON Participant with recorded consent
 * (DelegateAppointmentGuard, 422). Keeping an ACTIVE row ACTIVE with the
 * same Role is not an appointment and is not re-checked (such a row
 * simply stays ineffective while the Person is not a consented member).
 * One transaction, locked Production -> Participant -> delegate (§5.4);
 * DELEGATE_REACTIVATED / DELEGATE_DEACTIVATED (reason MANAGER) are
 * recorded with the change.
 */
final class UpdateProductionDelegateUseCase
{
    public const REASON_MANAGER = 'MANAGER';

    private ProductionDelegateRepositoryInterface $delegates;
    private ProductionRepositoryInterface $productions;
    private PersonRepositoryInterface $people;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;
    private DelegateAppointmentGuard $guard;
    private ParticipantEventRecorder $events;

    public function __construct(
        ProductionDelegateRepositoryInterface $delegates,
        ProductionRepositoryInterface $productions,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions,
        DelegateAppointmentGuard $guard,
        ParticipantEventRecorder $events
    ) {
        $this->delegates = $delegates;
        $this->productions = $productions;
        $this->people = $people;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->guard = $guard;
        $this->events = $events;
    }

    public function execute(UpdateProductionDelegateCommand $command): ProductionDelegateResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionDelegateAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $delegateId = ProductionDelegateId::fromString($command->productionDelegateId);
        $delegate = $this->delegates->findById($delegateId);

        if (! $delegate) {
            throw new ProductionDelegateNotFoundException($command->productionDelegateId);
        }

        $production = $this->productions->findById($delegate->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($delegate->productionId()->toString());
        }

        $newRole = RoleKey::fromString($command->role);

        // Both the delegate row's CURRENT Role and the Role being set must
        // be within what this requester may manage - a 代理人 must not be
        // able to touch an existing TICKET_MANAGER/etc. row (even to
        // "change" it), nor use this endpoint to grant a Role outside
        // their own permitted set by switching an allowed row's Role to a
        // disallowed one.
        if (! $this->authorization->canManageProductionDelegateRole($requester, $production, $delegate->role())
            || ! $this->authorization->canManageProductionDelegateRole($requester, $production, $newRole)
        ) {
            throw new ProductionDelegateAccessDeniedException(
                'Only the PrimaryManager, or a 代理人 (PARTICIPANT_MANAGER + REHEARSAL_MANAGER) managing 代理人/会計担当/受付担当, can change this Role.'
            );
        }

        $makeInactive = $command->status === ProductionDelegate::STATUS_INACTIVE;

        $delegate = $this->transactions->run(function () use ($production, $delegate, $delegateId, $newRole, $makeInactive, $requester): ProductionDelegate {
            if ($this->productions->findByIdForUpdate($production->id()) === null) {
                throw new ProductionNotFoundException($production->id()->toString());
            }

            // Participant rows before the delegate row (§5.4); whether this is
            // an appointment is decided on the locked delegate row below.
            $ineligible = $makeInactive ? null : $this->guard->lockAndCheck($production->id(), $delegate->personId());

            $locked = null;

            foreach ($this->delegates->findByProductionAndPersonForUpdate($production->id(), $delegate->personId()) as $row) {
                if ($row->id()->equals($delegateId)) {
                    $locked = $row;
                }
            }

            if ($locked === null) {
                throw new ProductionDelegateNotFoundException($delegateId->toString());
            }

            $wasActive = $locked->isActive();

            if (! $makeInactive && (! $wasActive || ! $locked->role()->equals($newRole))) {
                DelegateAppointmentGuard::assertNoReason($ineligible);
            }

            $now = new DateTimeImmutable();

            $locked->changeRole($newRole, $requester->id());

            if ($makeInactive) {
                $locked->deactivate($requester->id());
            } else {
                $locked->activate($requester->id());
            }

            $this->delegates->save($locked);

            if ($wasActive && $makeInactive) {
                $this->events->delegateDeactivated($locked, null, $requester->id(), self::REASON_MANAGER, $now);
            } elseif (! $wasActive && ! $makeInactive) {
                $this->events->delegateAppointed($locked, $requester->id(), true, $now);
            }

            return $locked;
        });

        return ProductionDelegateResult::fromDomain(
            $delegate,
            $this->people->findById($delegate->personId()),
            $this->authorization->delegateIneffectiveReason($delegate)
        );
    }
}
