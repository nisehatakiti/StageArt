<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * StageArt メール招待によるProductionParticipant追加機能 (§8): the
 * REST-facing `GET /people?email=&production_id=` search. This round's
 * instruction asked for `GET /people?email=...` gated by the existing
 * PrimaryManager/PARTICIPANT_MANAGER (canManageParticipants) capability
 * - but that capability is defined per-Production
 * (ProductionAuthorizationService::canManageParticipants(Person,
 * Production)), and a bare `email`-only query has no Production to
 * check it against. `production_id` was therefore added as a required
 * query parameter (a deliberate, disclosed addition beyond the literal
 * `GET /people?email=...` shape - see this round's final report) so the
 * instructed permission model has something to evaluate.
 */
final class SearchPersonByEmailUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private FindPersonByEmailUseCase $findPersonByEmail;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        FindPersonByEmailUseCase $findPersonByEmail
    ) {
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->findPersonByEmail = $findPersonByEmail;
    }

    public function execute(SearchPersonByEmailQuery $query): PersonSummaryResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can search for a Person by email.'
            );
        }

        $person = $this->findPersonByEmail->execute($query->email);

        if ($person === null) {
            throw new PersonNotFoundException("No Person found for email {$query->email}.");
        }

        return $person;
    }
}
