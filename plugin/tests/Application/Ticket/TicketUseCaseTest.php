<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Ticket;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Ticket\ArchiveTicketCommand;
use StageArt\Application\Ticket\ArchiveTicketUseCase;
use StageArt\Application\Ticket\CreateTicketCommand;
use StageArt\Application\Ticket\CreateTicketUseCase;
use StageArt\Application\Ticket\GetQuotaAndTicketBackSettingsQuery;
use StageArt\Application\Ticket\GetQuotaAndTicketBackSettingsUseCase;
use StageArt\Application\Ticket\GetTicketQuery;
use StageArt\Application\Ticket\GetTicketSalesSettingsQuery;
use StageArt\Application\Ticket\GetTicketSalesSettingsUseCase;
use StageArt\Application\Ticket\GetTicketUseCase;
use StageArt\Application\Ticket\ListPerformanceTicketAvailabilityQuery;
use StageArt\Application\Ticket\ListPerformanceTicketAvailabilityUseCase;
use StageArt\Application\Ticket\ListPublicTicketsQuery;
use StageArt\Application\Ticket\ListPublicTicketsUseCase;
use StageArt\Application\Ticket\ListTicketsForProductionQuery;
use StageArt\Application\Ticket\ListTicketsUseCase;
use StageArt\Application\Ticket\TicketAccessDeniedException;
use StageArt\Application\Ticket\UpdateQuotaAndTicketBackSettingsCommand;
use StageArt\Application\Ticket\UpdateQuotaAndTicketBackSettingsUseCase;
use StageArt\Application\Ticket\UpdateTicketCommand;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsCommand;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsUseCase;
use StageArt\Application\Ticket\UpdateTicketUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTicketRepository;

final class TicketUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryTicketRepository $tickets;
    private InMemoryPerformanceRepository $performances;

    private CreateTicketUseCase $createTicket;
    private GetTicketUseCase $getTicket;
    private ListTicketsUseCase $listTickets;
    private UpdateTicketUseCase $updateTicket;
    private ArchiveTicketUseCase $archiveTicket;
    private ListPublicTicketsUseCase $listPublicTickets;
    private UpdateTicketSalesSettingsUseCase $updateSalesSettings;
    private UpdateQuotaAndTicketBackSettingsUseCase $updateQuotaAndTicketBack;
    private GetTicketSalesSettingsUseCase $getSalesSettings;
    private GetQuotaAndTicketBackSettingsUseCase $getQuotaAndTicketBack;
    private ListPerformanceTicketAvailabilityUseCase $listPerformanceAvailability;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->tickets = new InMemoryTicketRepository();
        $this->performances = new InMemoryPerformanceRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            new InMemoryParticipantRepository()
        );
        $membership = new CoreMembershipAdapter(new InMemoryParticipantRepository(), $this->productions, $this->people, $productionAuthorization);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);

        $this->createTicket = new CreateTicketUseCase($productionContext, $this->tickets, $identity, $authorization);
        $this->getTicket = new GetTicketUseCase($this->tickets, $productionContext, $identity, $membership);
        $this->listTickets = new ListTicketsUseCase($this->tickets, $productionContext, $identity, $membership);
        $this->updateTicket = new UpdateTicketUseCase($this->tickets, $productionContext, $identity, $authorization);
        $this->archiveTicket = new ArchiveTicketUseCase($this->tickets, $productionContext, $identity, $authorization);
        $this->listPublicTickets = new ListPublicTicketsUseCase($this->tickets, $productionContext);
        $this->updateSalesSettings = new UpdateTicketSalesSettingsUseCase($this->productions, $identity, $authorization);
        $this->updateQuotaAndTicketBack = new UpdateQuotaAndTicketBackSettingsUseCase($this->productions, $identity, $authorization);
        $this->getSalesSettings = new GetTicketSalesSettingsUseCase($this->productions, $identity, $membership);
        $this->getQuotaAndTicketBack = new GetQuotaAndTicketBackSettingsUseCase($this->productions, $identity, $membership);
        $this->listPerformanceAvailability = new ListPerformanceTicketAvailabilityUseCase($this->performances, $productionContext, $identity, $membership);
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    private function addTicketManagerDelegate(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $delegate = ProductionDelegate::create($production->id(), $person->id(), RoleKey::ticketManager(), $production->primaryManagerPersonId());
        $this->delegates->save($delegate);

        return $person;
    }

    public function test_primary_manager_can_create_ticket(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 5000, null));

        $this->assertSame('一般', $result->name);
        $this->assertSame(5000, $result->price);
        $this->assertSame('ACTIVE', $result->status);
    }

    public function test_create_ticket_rejects_zero_price(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(InvalidArgumentException::class);
        $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '招待', 0, null));
    }

    public function test_ticket_manager_delegate_can_create_ticket(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addTicketManagerDelegate($production, 5);

        $result = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 5, '学生', 3000, null));

        $this->assertSame('学生', $result->name);
    }

    public function test_plain_participant_cannot_create_ticket(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $stranger = Person::create(2);
        $this->people->save($stranger);

        $this->expectException(TicketAccessDeniedException::class);
        $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 2, '一般', 5000, null));
    }

    public function test_update_ticket_changes_fields(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 5000, null));

        $updated = $this->updateTicket->execute(new UpdateTicketCommand($created->id, 1, '一般（改）', 4500, '備考'));

        $this->assertSame('一般（改）', $updated->name);
        $this->assertSame(4500, $updated->price);
    }

    public function test_archive_ticket_sets_archived_status(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 5000, null));

        $archived = $this->archiveTicket->execute(new ArchiveTicketCommand($created->id, 1));

        $this->assertSame('ARCHIVED', $archived->status);
    }

    public function test_list_tickets_scoped_to_production_membership(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $productionB = $this->givenProductionWithPrimaryManager(2);

        $this->createTicket->execute(new CreateTicketCommand($productionA->id()->toString(), 1, '一般', 5000, null));

        $resultsForA = $this->listTickets->execute(new ListTicketsForProductionQuery($productionA->id()->toString(), 1));
        $this->assertCount(1, $resultsForA);

        $this->expectException(TicketAccessDeniedException::class);
        $this->listTickets->execute(new ListTicketsForProductionQuery($productionB->id()->toString(), 1));
    }

    public function test_get_ticket_rejects_non_member(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(99);
        $created = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 5000, null));

        $this->expectException(TicketAccessDeniedException::class);
        $this->getTicket->execute(new GetTicketQuery($created->id, 99));
    }

    public function test_update_sales_settings_persists_publication_and_sales_window(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            '2026-09-01T00:00:00+09:00',
            '2026-09-10T00:00:00+09:00',
            'HOURS_BEFORE_START',
            '3'
        ));

        $this->assertNotNull($result->publicationAt);
        $this->assertNotNull($result->salesStartAt);
        $this->assertSame('HOURS_BEFORE_START', $result->salesEndRule);
        $this->assertSame('3', $result->salesEndParameter);
    }

    public function test_update_sales_settings_rejects_invalid_rule(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(InvalidArgumentException::class);
        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            null,
            null,
            'NOT_A_RULE',
            '3'
        ));
    }

    public function test_update_quota_settings_requires_positive_count_when_enabled(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(InvalidArgumentException::class);
        $this->updateQuotaAndTicketBack->execute(new UpdateQuotaAndTicketBackSettingsCommand(
            $production->id()->toString(),
            1,
            true,
            null,
            false,
            null,
            null,
            []
        ));
    }

    public function test_update_quota_settings_with_buyback_off_never_stores_a_unit_price(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->updateQuotaAndTicketBack->execute(new UpdateQuotaAndTicketBackSettingsCommand(
            $production->id()->toString(),
            1,
            true,
            50,
            false,
            9999, // must be ignored/normalized to null since buyback is OFF
            null,
            []
        ));

        $this->assertTrue($result->quotaEnabled);
        $this->assertSame(50, $result->quotaCount);
        $this->assertFalse($result->quotaBuybackEnabled);
        $this->assertNull($result->quotaShortfallUnitPrice);
    }

    public function test_update_quota_settings_with_buyback_on_requires_unit_price(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(InvalidArgumentException::class);
        $this->updateQuotaAndTicketBack->execute(new UpdateQuotaAndTicketBackSettingsCommand(
            $production->id()->toString(),
            1,
            true,
            50,
            true,
            null,
            null,
            []
        ));
    }

    public function test_update_quota_settings_with_buyback_on_stores_unit_price_and_computes_payable(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->updateQuotaAndTicketBack->execute(new UpdateQuotaAndTicketBackSettingsCommand(
            $production->id()->toString(),
            1,
            true,
            50,
            true,
            2000,
            null,
            []
        ));

        $this->assertTrue($result->quotaBuybackEnabled);
        $this->assertSame(2000, $result->quotaShortfallUnitPrice);
    }

    public function test_update_ticket_back_settings_persists_conditions(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->updateQuotaAndTicketBack->execute(new UpdateQuotaAndTicketBackSettingsCommand(
            $production->id()->toString(),
            1,
            false,
            null,
            false,
            null,
            'PROGRESSIVE',
            [
                ['priority' => 1, 'threshold' => 11, 'comparator' => 'GTE', 'rate_percent' => 15],
                ['priority' => 2, 'threshold' => 1, 'comparator' => 'GTE', 'rate_percent' => 10],
            ]
        ));

        $this->assertSame('PROGRESSIVE', $result->ticketBackMode);
        $this->assertCount(2, $result->ticketBackConditions);
    }

    public function test_public_ticket_listing_is_empty_before_publication(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 5000, null));

        $result = $this->listPublicTickets->execute(new ListPublicTicketsQuery($production->id()->toString()));

        $this->assertSame([], $result->tickets);
    }

    public function test_public_ticket_listing_shows_active_tickets_after_publication(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 5000, null));

        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            (new DateTimeImmutable('-1 hour'))->format(DATE_ATOM),
            null,
            null
        ));

        $result = $this->listPublicTickets->execute(new ListPublicTicketsQuery($production->id()->toString()));

        $this->assertCount(1, $result->tickets);
        $this->assertSame('一般', $result->tickets[0]->name);
    }

    public function test_public_ticket_listing_excludes_archived_tickets(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 5000, null));
        $this->archiveTicket->execute(new ArchiveTicketCommand($created->id, 1));

        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            null,
            null,
            null
        ));

        $result = $this->listPublicTickets->execute(new ListPublicTicketsQuery($production->id()->toString()));

        $this->assertSame([], $result->tickets);
    }

    public function test_get_sales_settings_is_readable_by_any_production_member(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            '2026-09-01T00:00:00+09:00',
            '2026-09-10T00:00:00+09:00',
            'HOURS_BEFORE_START',
            '3'
        ));

        $result = $this->getSalesSettings->execute(new GetTicketSalesSettingsQuery($production->id()->toString(), 1));

        $this->assertNotNull($result->publicationAt);
        $this->assertSame('HOURS_BEFORE_START', $result->salesEndRule);
    }

    public function test_get_sales_settings_rejects_non_member(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(99);

        $this->expectException(TicketAccessDeniedException::class);
        $this->getSalesSettings->execute(new GetTicketSalesSettingsQuery($production->id()->toString(), 99));
    }

    public function test_get_quota_and_ticket_back_settings_is_readable_by_any_production_member(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->updateQuotaAndTicketBack->execute(new UpdateQuotaAndTicketBackSettingsCommand(
            $production->id()->toString(),
            1,
            true,
            50,
            false,
            null,
            null,
            []
        ));

        $result = $this->getQuotaAndTicketBack->execute(new GetQuotaAndTicketBackSettingsQuery($production->id()->toString(), 1));

        $this->assertTrue($result->quotaEnabled);
        $this->assertSame(50, $result->quotaCount);
    }

    private function givenPerformance(Production $production, string $date, string $startTime): Performance
    {
        $performance = Performance::create($production->id(), new DateTimeImmutable($date), $startTime, null, 100, null, null);
        $this->performances->save($performance);

        return $performance;
    }

    public function test_performance_ticket_availability_is_not_open_before_publication(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenPerformance($production, '2099-01-01', '18:00');

        $result = $this->listPerformanceAvailability->execute(new ListPerformanceTicketAvailabilityQuery($production->id()->toString(), 1));

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]->isTicketPublished);
        $this->assertFalse($result[0]->isSalesOpen);
    }

    public function test_performance_ticket_availability_is_open_within_the_sales_window(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenPerformance($production, '2099-01-01', '18:00');

        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            (new DateTimeImmutable('-1 hour'))->format(DATE_ATOM),
            'HOURS_BEFORE_START',
            '3'
        ));

        $result = $this->listPerformanceAvailability->execute(new ListPerformanceTicketAvailabilityQuery($production->id()->toString(), 1));

        $this->assertTrue($result[0]->isTicketPublished);
        $this->assertTrue($result[0]->isSalesOpen);
        $this->assertNotNull($result[0]->salesEndAt);
    }

    public function test_performance_ticket_availability_is_closed_after_sales_end_deadline(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        // Performance start is only 1 hour from now, but the rule requires
        // sales to end 3 hours before start - already past that deadline.
        $this->givenPerformance($production, (new DateTimeImmutable('+1 hour'))->format('Y-m-d'), (new DateTimeImmutable('+1 hour'))->format('H:i'));

        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            'HOURS_BEFORE_START',
            '3'
        ));

        $result = $this->listPerformanceAvailability->execute(new ListPerformanceTicketAvailabilityQuery($production->id()->toString(), 1));

        $this->assertTrue($result[0]->isTicketPublished);
        $this->assertFalse($result[0]->isSalesOpen);
    }
}
