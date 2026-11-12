<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

/**
 * StageArt メール招待によるProductionParticipant追加機能 (§8): unlike
 * GetPersonByIdQuery (which only requires the caller to be logged in -
 * any authenticated Person may look another up by id), this search is
 * gated by the Production-scoped canManageParticipants() capability per
 * this round's explicit instruction - so, unlike a bare `GET /people?
 * email=...`, it always needs a Production to check that capability
 * against. See SearchPersonByEmailUseCase's own docblock for why
 * `productionId` was added to this endpoint's shape.
 */
final class SearchPersonByEmailQuery
{
    public string $email;
    public string $productionId;
    public int $requestedByWordPressUserId;

    public function __construct(string $email, string $productionId, int $requestedByWordPressUserId)
    {
        $this->email = $email;
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
