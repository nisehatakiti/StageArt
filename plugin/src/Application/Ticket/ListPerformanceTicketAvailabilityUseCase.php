<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use DateTimeImmutable;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Contract\ProductionTicketSettings;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Ticket\SalesEndRule;

/**
 * "公演スケジュールごとの販売可能状態を確認できる" - a read-only view, not a
 * new Reservation capability. `isSalesOpen` recomputes the exact same
 * checks CreateReservationUseCase itself enforces at actual reservation
 * time (公開済み / 販売開始済み / 販売終了前 / 開演前) so this display can
 * never claim a Performance is sellable when a real reservation attempt
 * would in fact be rejected, without duplicating that enforcement's own
 * business rule anywhere new. Depends directly on
 * `Domain\Performance\PerformanceRepositoryInterface` and
 * `Domain\Ticket\SalesEndRule` (this Module's own Domain), not on the
 * Reservation Module's Application-layer `SalesWindowResolver` - Modules
 * do not depend on each other's Application internals in this codebase
 * (see TicketModuleBootstrap's own docblock for the established
 * boundary), so the same small computation is composed here from the
 * same underlying confirmed rule instead of importing across that
 * boundary.
 */
final class ListPerformanceTicketAvailabilityUseCase
{
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->performances = $performances;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    /**
     * @return PerformanceTicketAvailabilityResult[]
     */
    public function execute(ListPerformanceTicketAvailabilityQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TicketAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new TicketAccessDeniedException('You must be a member of this Production to view its Performance ticket availability.');
        }

        $settings = $this->productionContext->getProductionTicketSettings($productionId);
        $now = new DateTimeImmutable();

        return array_map(
            fn (Performance $performance): PerformanceTicketAvailabilityResult => $this->availabilityFor($performance, $settings, $now),
            $this->performances->findByProductionId($productionId)
        );
    }

    private function availabilityFor(Performance $performance, ?ProductionTicketSettings $settings, DateTimeImmutable $now): PerformanceTicketAvailabilityResult
    {
        $isPublished = $settings !== null
            && $settings->ticketPublicationAt !== null
            && new DateTimeImmutable($settings->ticketPublicationAt) <= $now;

        $salesStartAt = $settings !== null && $settings->ticketSalesStartAt !== null
            ? new DateTimeImmutable($settings->ticketSalesStartAt)
            : null;

        $performanceStart = $performance->startDateTime();

        $salesEndAt = $settings !== null && $settings->ticketSalesEndRule !== null && $settings->ticketSalesEndParameter !== null
            ? SalesEndRule::fromStored($settings->ticketSalesEndRule, $settings->ticketSalesEndParameter)->computeDeadline($performanceStart)
            : null;

        $isSalesOpen = $isPublished
            && $salesStartAt !== null && $now >= $salesStartAt
            && ($salesEndAt === null || $now < $salesEndAt)
            && $now < $performanceStart;

        return new PerformanceTicketAvailabilityResult(
            $performance->id()->toString(),
            $performance->performanceDate()->format('Y-m-d'),
            $performance->startTime(),
            $performance->status()->toString(),
            $isPublished,
            $salesStartAt?->format(DATE_ATOM),
            $salesEndAt?->format(DATE_ATOM),
            $isSalesOpen
        );
    }
}
