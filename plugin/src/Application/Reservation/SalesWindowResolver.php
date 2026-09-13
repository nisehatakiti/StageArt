<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use DateTimeImmutable;
use StageArt\Core\Contract\ProductionTicketSettings;
use StageArt\Domain\Ticket\SalesEndRule;

/**
 * Bridges Core's `ProductionTicketSettings` DTO (plain strings) with the
 * Ticket Module's own `Domain\Ticket\SalesEndRule` Value Object, so every
 * Reservation UseCase computes "when does sales end for this specific
 * Performance" the exact same way (§8's "Productionの販売終了ルール +
 * Performanceの開演日時 = 実際の販売終了日時").
 */
final class SalesWindowResolver
{
    private function __construct()
    {
    }

    public static function salesEndAt(ProductionTicketSettings $settings, DateTimeImmutable $performanceStartDateTime): ?DateTimeImmutable
    {
        if ($settings->ticketSalesEndRule === null || $settings->ticketSalesEndParameter === null) {
            return null;
        }

        return SalesEndRule::fromStored($settings->ticketSalesEndRule, $settings->ticketSalesEndParameter)
            ->computeDeadline($performanceStartDateTime);
    }

    public static function salesStartAt(ProductionTicketSettings $settings): ?DateTimeImmutable
    {
        return $settings->ticketSalesStartAt !== null ? new DateTimeImmutable($settings->ticketSalesStartAt) : null;
    }

    public static function isTicketPublished(ProductionTicketSettings $settings): bool
    {
        return $settings->ticketPublicationAt !== null && new DateTimeImmutable($settings->ticketPublicationAt) <= new DateTimeImmutable();
    }
}
