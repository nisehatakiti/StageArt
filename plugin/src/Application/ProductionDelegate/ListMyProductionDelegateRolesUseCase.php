<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;

/**
 * PR #7 R14-B stage 9 (design doc §10 「公演の画面（担当者本人）」【推奨】,
 * §4.6; API added with the user's approval): `GET
 * /productions/{id}/delegates/me` - the logged-in Person's OWN delegate rows
 * in one Production, each with `effective` and `ineffective_reason`
 * (INACTIVE / NOT_ACTIVE_PARTICIPANT; CONSENT_UNCONFIRMED until 2026-11-27), so the Person
 * can see why a role of theirs grants nothing.
 *
 * - Any logged-in Person may call it; it only ever returns their own rows
 *   (an empty list when they have none) - never anyone else's, and no
 *   name, email or other Person's data.
 * - A Production that does not exist is 404, as on the other delegate
 *   routes.
 * - Read only; the reasons come from the same
 *   ProductionAuthorizationService::delegateIneffectiveReason() as the
 *   managers' delegate list.
 */
final class ListMyProductionDelegateRolesUseCase
{
    private ProductionDelegateRepositoryInterface $delegates;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ProductionDelegateRepositoryInterface $delegates,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->delegates = $delegates;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    /**
     * @return MyProductionDelegateRoleResult[]
     */
    public function execute(ListProductionDelegatesQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionDelegateAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        $own = array_values(array_filter(
            $this->delegates->findByProductionId($production->id()),
            static fn (ProductionDelegate $delegate): bool => $delegate->personId()->equals($requester->id())
        ));

        return array_map(
            fn (ProductionDelegate $delegate): MyProductionDelegateRoleResult => MyProductionDelegateRoleResult::fromDomain(
                $delegate,
                $this->authorization->delegateIneffectiveReason($delegate)
            ),
            $own
        );
    }
}
