<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

use InvalidArgumentException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;

/**
 * StageArt メンバー管理: Person ID検索 (担当者権限をメンバー管理へ統合・整理
 * §1/§2-A instruction): confirmed via investigation that no Person search/
 * lookup API existed anywhere in StageArt before this - this is the first
 * one. Deliberately requires only "is a real, logged-in StageArt Person"
 * (same resolveCurrentPerson() gate GetCurrentPersonUseCase already uses),
 * not any Production-specific management Capability - looking someone's
 * name up by a UUID they (or an admin) already have is not itself a
 * Production-scoped action; the actual "add as a member" step remains
 * gated by CreateParticipantUseCase's own existing
 * canManageParticipants() check, unchanged by this class.
 */
final class GetPersonByIdUseCase
{
    private OrganizationAuthorizationService $authorization;
    private PersonRepositoryInterface $people;

    public function __construct(OrganizationAuthorizationService $authorization, PersonRepositoryInterface $people)
    {
        $this->authorization = $authorization;
        $this->people = $people;
    }

    public function execute(GetPersonByIdQuery $query): PersonSummaryResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new CurrentPersonNotFoundException('No StageArt Person is linked to this WordPress user.');
        }

        try {
            $personId = PersonId::fromString($query->personId);
        } catch (InvalidArgumentException $exception) {
            throw new PersonNotFoundException('No Person exists with this id.');
        }

        $person = $this->people->findById($personId);

        if (! $person) {
            throw new PersonNotFoundException('No Person exists with this id.');
        }

        return PersonSummaryResult::fromDomain($person);
    }
}
