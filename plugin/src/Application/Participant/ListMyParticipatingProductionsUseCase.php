<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * The 「参加している公演・活動」 Read Model: GetMyDashboardUseCase's own
 * resolveInvolvedProductionIds() already establishes the precedent this
 * mirrors (PrimaryManager ∪ active Delegate ∪ active Person-Participant,
 * resolved via bulk findBySubject()/findByIds() - never upcoming
 * Rehearsal/Attendance, which answers a different question). This
 * UseCase answers only "which Productions is the caller a current
 * PERSON Participant of" per docs/03-BusinessFlowUXClarifications.md §07
 * ("本人確認・承認を経て参加を確定する"): ACTIVE Participant status is the
 * confirmed-participation signal, matching ParticipantStatus's own
 * DRAFT/PENDING/REJECTED/CANCELLED/INACTIVE not being "participating".
 *
 * Deliberately does not filter by the Production's own lifecycle status
 * (PLANNING/ACTIVE/COMPLETED/ARCHIVED/CANCELLED) - resolveInvolvedProductionIds()
 * does not either, and no existing spec defines which Production
 * statuses belong in a Person's own participation list.
 */
final class ListMyParticipatingProductionsUseCase
{
    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    /**
     * @return MyParticipatingProductionResult[]
     */
    public function execute(ListMyParticipatingProductionsQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $activeStatus = ParticipantStatus::active();

        $activeParticipants = array_values(array_filter(
            $this->participants->findBySubject(ParticipantSubjectType::person(), $requester->id()->toString()),
            static fn (Participant $participant): bool => $participant->status()->equals($activeStatus)
        ));

        if ($activeParticipants === []) {
            return [];
        }

        $productions = $this->productions->findByIds(array_map(
            static fn (Participant $participant) => $participant->productionId(),
            $activeParticipants
        ));

        /** @var array<string, \StageArt\Domain\Production\Production> $productionsById */
        $productionsById = [];
        foreach ($productions as $production) {
            $productionsById[$production->id()->toString()] = $production;
        }

        $results = [];
        foreach ($activeParticipants as $participant) {
            $production = $productionsById[$participant->productionId()->toString()] ?? null;

            // Defensive: a Production could in principle be deleted out
            // from under an existing Participant row. Skip rather than
            // error - the caller's participation list simply omits it.
            if (! $production) {
                continue;
            }

            $results[] = MyParticipatingProductionResult::fromDomain($participant, $production);
        }

        return $results;
    }
}
