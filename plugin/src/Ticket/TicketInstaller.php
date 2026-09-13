<?php

declare(strict_types=1);

namespace StageArt\Ticket;

/**
 * StageArt Core/Module Architecture (docs/architecture/
 * WordPressPluginModuleBoundary.md): the Ticket Module's own three
 * tables (tickets, reservations, issued_tickets), following
 * RehearsalInstaller/PerformanceInstaller's exact precedent - Core's own
 * `Infrastructure\WordPress\Schema\Installer::install()` calls this
 * rather than creating these tables itself.
 *
 * Reservation and Issued Ticket both live here (not split into a
 * separate `ReservationInstaller`) because Phase 3 instruction §35 groups
 * all three DB tables under one "DB設計" section and the Ticket/
 * Reservation/IssuedTicket Domains are wired together as one Module
 * Bootstrap (see TicketModuleBootstrap) - splitting the *Installer*
 * across a Module boundary that the *Bootstrap* does not itself observe
 * would only add indirection without a real ownership benefit.
 */
final class TicketInstaller
{
    /**
     * @param \wpdb $wpdb
     */
    public static function install($wpdb, string $charsetCollate): void
    {
        $tickets = $wpdb->prefix . 'stageart_tickets';
        $reservations = $wpdb->prefix . 'stageart_reservations';
        $issuedTickets = $wpdb->prefix . 'stageart_issued_tickets';

        dbDelta("CREATE TABLE {$tickets} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            price INT NOT NULL,
            remarks TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");

        /*
         * `reservation_number` is the user-facing identifier (Reservation.md
         * "ReservationNumberはReservationIdとは別の識別子として扱う") - UNIQUE
         * so ReservationNumber::generate()'s random alphabet can never
         * silently collide into an overwrite. `created_by`/`updated_by`
         * are nullable (Phase 3 instruction §10: a general-audience
         * booking is created via self-service with no authenticated
         * Person at all - see Domain\Reservation\Reservation's own
         * docblock). `attributed_person_id` is new in Phase 4 (Check-in/
         * 精算/会計連携) - see Reservation::class's own docblock for why
         * this "誰扱い" fact is distinct from both `created_by` and the
         * Booker.
         */
        dbDelta("CREATE TABLE {$reservations} (
            id CHAR(36) NOT NULL,
            reservation_number VARCHAR(20) NOT NULL,
            performance_id CHAR(36) NOT NULL,
            ticket_id CHAR(36) NOT NULL,
            booker_name VARCHAR(255) NOT NULL,
            booker_email VARCHAR(255) NOT NULL,
            guest_count INT NOT NULL,
            price_snapshot INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'RESERVED',
            attributed_person_id CHAR(36) NULL,
            created_by CHAR(36) NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reservation_number (reservation_number),
            KEY performance_id (performance_id),
            KEY ticket_id (ticket_id),
            KEY booker_email (booker_email)
        ) {$charsetCollate};");

        /*
         * UNIQUE(reservation_id): V1's 1 Reservation : 1 IssuedTicket
         * relationship (Phase 3 instruction §11/§22) is enforced at the
         * DB layer too, not just by CreateReservationUseCase's own call
         * pattern.
         */
        dbDelta("CREATE TABLE {$issuedTickets} (
            id CHAR(36) NOT NULL,
            reservation_id CHAR(36) NOT NULL,
            performance_id CHAR(36) NOT NULL,
            ticket_id CHAR(36) NOT NULL,
            guest_count INT NOT NULL,
            issued_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reservation_id (reservation_id),
            KEY performance_id (performance_id)
        ) {$charsetCollate};");
    }
}
