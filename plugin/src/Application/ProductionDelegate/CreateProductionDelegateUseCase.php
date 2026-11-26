<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use DateTimeImmutable;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * `POST /productions/{id}/delegates`.
 *
 * R14-B §4.6 / §7.1 (stage 4):
 * - The target must be an ACTIVE PERSON Participant of the Production
 *   with recorded consent (422 target_not_participant / target_not_consented,
 *   DelegateAppointmentGuard), checked under the Production lock with the
 *   Participant rows locked (Production -> Participant -> delegate, §5.4).
 * - A row of the same (Production, Person, Role) left INACTIVE is made
 *   ACTIVE again instead of a second row (the key is unique) -
 *   DELEGATE_REACTIVATED; a new row is DELEGATE_APPOINTED. An already
 *   ACTIVE row is 409 already_exists, as before.
 * - Authorization (canManageProductionDelegateRole) is unchanged.
 */
final class CreateProductionDelegateUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionDelegateRepositoryInterface $delegates;
    private PersonRepositoryInterface $people;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;
    private DelegateAppointmentGuard $guard;
    private ParticipantEventRecorder $events;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionDelegateRepositoryInterface $delegates,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions,
        DelegateAppointmentGuard $guard,
        ParticipantEventRecorder $events
    ) {
        $this->productions = $productions;
        $this->delegates = $delegates;
        $this->people = $people;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->guard = $guard;
        $this->events = $events;
    }

    public function execute(CreateProductionDelegateCommand $command): ProductionDelegateResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionDelegateAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        $role = RoleKey::fromString($command->role);

        if (! $this->authorization->canManageProductionDelegateRole($requester, $production, $role)) {
            throw new ProductionDelegateAccessDeniedException(
                'Only the PrimaryManager, or a 代理人 (PARTICIPANT_MANAGER + REHEARSAL_MANAGER) managing 代理人/会計担当/受付担当, can assign this Role.'
            );
        }

        $targetPersonId = PersonId::fromString($command->personId);
        $targetPerson = $this->people->findById($targetPersonId);

        if (! $targetPerson) {
            throw new ProductionDelegateTargetNotEligibleException('The target Person does not exist.');
        }

        $delegate = $this->transactions->run(
            function () use ($production, $targetPersonId, $role, $requester): ProductionDelegate {
                if ($this->productions->findByIdForUpdate($production->id()) === null) {
                    throw new ProductionNotFoundException($production->id()->toString());
                }

                $this->guard->assertEligible($production->id(), $targetPersonId);

                $existing = null;

                foreach ($this->delegates->findByProductionAndPersonForUpdate($production->id(), $targetPersonId) as $row) {
                    if ($row->role()->equals($role)) {
                        $existing = $row;
                    }
                }

                $now = new DateTimeImmutable();

                if ($existing !== null) {
                    if ($existing->isActive()) {
                        throw new ProductionDelegateAlreadyExistsException(
                            'A ProductionDelegate with this Role already exists for this Person on this Production.'
                        );
                    }

                    $existing->activate($requester->id());
                    $this->delegates->save($existing);
                    $this->events->delegateAppointed($existing, $requester->id(), true, $now);

                    return $existing;
                }

                $delegate = ProductionDelegate::create($production->id(), $targetPersonId, $role, $requester->id());
                $this->delegates->save($delegate);
                $this->events->delegateAppointed($delegate, $requester->id(), false, $now);

                return $delegate;
            }
        );

        return ProductionDelegateResult::fromDomain($delegate, $targetPerson, null);
    }
}
