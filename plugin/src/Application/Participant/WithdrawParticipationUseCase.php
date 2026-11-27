<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\RoleKey;
use Throwable;

/**
 * R14-B §5.2 / §7.2 `POST /participants/{id}/withdraw` (D9): the Person
 * leaves a Production themselves.
 *
 * - Only the logged-in Person's own PERSON Participant: someone else's
 *   (or a NAME_ONLY / ORGANIZATION row) is 403, a missing one 404.
 * - Locks: Production -> Participant -> delegate (§5.4).
 * - ACTIVE -> WITHDRAWN; already WITHDRAWN is an idempotent success (no
 *   write, no audit event, no notification); any other state is 409.
 * - The PrimaryManager cannot withdraw (409
 *   stageart_participant_withdraw_primary_manager: hand the role over
 *   first).
 * - In the same transaction every delegate row of the Person in this
 *   Production becomes INACTIVE (PARTICIPANT_WITHDRAWN) and WITHDRAWN /
 *   DELEGATE_DEACTIVATED are recorded. Attendance, settlement and
 *   accounting records are not touched (the row is kept).
 * - After the commit the PrimaryManager and the effective
 *   PARTICIPANT_MANAGERs are notified.
 */
final class WithdrawParticipationUseCase
{
    private const MEMBER_FALLBACK_NAME = 'メンバー';

    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private ProductionDelegateRepositoryInterface $delegates;
    private ProductionAuthorizationService $authorization;
    private ParticipantDelegateRoleReleaser $delegateRoles;
    private ParticipantEventRecorder $events;
    private ParticipantConsentNotifier $notifier;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        ProductionDelegateRepositoryInterface $delegates,
        ProductionAuthorizationService $authorization,
        ParticipantDelegateRoleReleaser $delegateRoles,
        ParticipantEventRecorder $events,
        ParticipantConsentNotifier $notifier,
        TransactionManagerInterface $transactions
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->delegates = $delegates;
        $this->authorization = $authorization;
        $this->delegateRoles = $delegateRoles;
        $this->events = $events;
        $this->notifier = $notifier;
        $this->transactions = $transactions;
    }

    public function execute(WithdrawParticipationCommand $command): WithdrawParticipationResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if ($person === null) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        try {
            $participantId = ParticipantId::fromString($command->participantId);
        } catch (InvalidArgumentException $exception) {
            throw new ParticipantNotFoundException($command->participantId);
        }

        $unlocked = $this->participants->findById($participantId);

        if ($unlocked === null) {
            throw new ParticipantNotFoundException($command->participantId);
        }

        self::assertOwnPersonParticipant($unlocked, $person);
        $now = new DateTimeImmutable();

        $outcome = $this->transactions->run(function () use ($unlocked, $participantId, $person, $now): array {
            $production = $this->productions->findByIdForUpdate($unlocked->productionId());
            $participant = $this->participants->findByIdForUpdate($participantId);

            if ($production === null || $participant === null) {
                throw new ParticipantNotFoundException($participantId->toString());
            }

            self::assertOwnPersonParticipant($participant, $person);

            if ($participant->status()->toString() === ParticipantStatus::WITHDRAWN) {
                return ['changed' => false, 'participant' => $participant, 'production' => $production];
            }

            if ($this->authorization->isPrimaryManager($person, $production)) {
                throw new ParticipantWithdrawPrimaryManagerException('The PrimaryManager must hand over the role before withdrawing.');
            }

            if (! $participant->isActive()) {
                throw new ParticipantWithdrawNotAllowedException('Only an ACTIVE Participant can withdraw.');
            }

            $participant->withdraw();
            $this->participants->save($participant);

            $this->delegateRoles->release(
                $production->id(),
                $person->id(),
                $person->id(),
                ParticipantDelegateRoleReleaser::PARTICIPANT_WITHDRAWN,
                $participant,
                $now
            );
            $this->events->withdrawn($participant, $now);

            return ['changed' => true, 'participant' => $participant, 'production' => $production];
        });

        /** @var Participant $participant */
        $participant = $outcome['participant'];

        if ($outcome['changed']) {
            $this->notifyManagers($outcome['production'], $participant, $person);
        }

        return new WithdrawParticipationResult(
            $participant->id()->toString(),
            $participant->productionId()->toString(),
            ParticipantStatus::WITHDRAWN
        );
    }

    private static function assertOwnPersonParticipant(Participant $participant, Person $person): void
    {
        if (! $participant->subjectType()->equals(ParticipantSubjectType::person())
            || $participant->subjectId() !== $person->id()->toString()
        ) {
            throw new ParticipantAccessDeniedException('Only the Participant themselves can withdraw.');
        }
    }

    /**
     * §7.2: the PrimaryManager and every effective PARTICIPANT_MANAGER - an
     * ACTIVE delegate row whose Person is an ACTIVE PERSON Participant
     * (§4.6; no consent needed since 2026-11-27) - except the Person who withdrew. Worked out
     * after the commit; a failure here only loses the notification.
     */
    private function notifyManagers(Production $production, Participant $participant, Person $person): void
    {
        try {
            $recipients = [$production->primaryManagerPersonId()->toString() => $production->primaryManagerPersonId()];
            $consented = [];

            foreach ($this->participants->findByProductionId($production->id()) as $member) {
                if ($member->isActivePerson()) {
                    $consented[$member->subjectId()] = true;
                }
            }

            foreach ($this->delegates->findByProductionId($production->id()) as $delegate) {
                $id = $delegate->personId()->toString();

                if ($delegate->isActive()
                    && $delegate->role()->equals(RoleKey::participantManager())
                    && isset($consented[$id])
                ) {
                    $recipients[$id] = $delegate->personId();
                }
            }

            unset($recipients[$person->id()->toString()]);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[StageArt participant notification] %s for %s not sent: %s',
                ParticipantConsentNotifier::TYPE_PARTICIPANT_WITHDRAWN,
                $participant->id()->toString(),
                get_class($exception)
            ));

            return;
        }

        $this->notifier->participantWithdrawn(
            $production,
            self::memberName($participant, $person),
            array_values($recipients),
            $participant->id()->toString()
        );
    }

    /** §11.1 「{公演側の氏名}さん」: the Production-side name, else the Person's own name. */
    private static function memberName(Participant $participant, Person $person): string
    {
        $displayName = $participant->displayName();

        if ($displayName !== null && trim($displayName) !== '') {
            return $displayName;
        }

        $name = trim(($person->familyName() ?? '') . ' ' . ($person->givenName() ?? ''));

        return $name !== '' ? $name : self::MEMBER_FALLBACK_NAME;
    }
}
