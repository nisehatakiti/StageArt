<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Questionnaire;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Questionnaire\QuestionnaireMailerInterface;
use StageArt\Application\Questionnaire\QuestionnairePublicUrlResolver;
use StageArt\Application\Questionnaire\SendQuestionnaireInvitesForPerformanceUseCase;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceStatus;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Questionnaire\Questionnaire;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationStatus;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryQuestionnaireInviteRecordRepository;
use StageArt\Tests\Support\InMemoryQuestionnaireRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;

final class SendQuestionnaireInvitesForPerformanceUseCaseTest extends TestCase
{
    private function buildUseCase(
        InMemoryPerformanceRepository $performances,
        InMemoryQuestionnaireRepository $questionnaires,
        InMemoryReservationRepository $reservations,
        InMemoryQuestionnaireInviteRecordRepository $inviteRecords,
        RecordingQuestionnaireMailer $mailer,
        InMemoryProductionRepository $productions,
        InMemoryProjectRepository $projects,
        InMemoryOrganizationRepository $organizations
    ): SendQuestionnaireInvitesForPerformanceUseCase {
        $productionContext = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver($projects), $organizations);
        $publicUrlResolver = new QuestionnairePublicUrlResolver($productionContext, 'https://dummy.stageart.top');

        return new SendQuestionnaireInvitesForPerformanceUseCase(
            $performances,
            $questionnaires,
            $reservations,
            $inviteRecords,
            $mailer,
            $publicUrlResolver,
            $productionContext
        );
    }

    private function givenPublishedQuestionnaireProduction(
        InMemoryOrganizationRepository $organizations,
        InMemoryProjectRepository $projects,
        InMemoryProductionRepository $productions,
        InMemoryQuestionnaireRepository $questionnaires
    ): Production {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organization->changeSlug(new OrganizationSlug('theatre-co'));
        $organizations->save($organization);

        $primaryManager = Person::create(1);
        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeSlug(new ProductionSlug('show-slug'));
        $productions->save($production);

        $questionnaire = Questionnaire::create($production->id(), 'アンケート', null, null);
        $questionnaire->publish(null);
        $questionnaires->save($questionnaire);

        return $production;
    }

    public function test_sends_one_email_to_checked_in_reservation_regardless_of_guest_count(): void
    {
        $performances = new InMemoryPerformanceRepository();
        $questionnaires = new InMemoryQuestionnaireRepository();
        $reservations = new InMemoryReservationRepository();
        $inviteRecords = new InMemoryQuestionnaireInviteRecordRepository();
        $mailer = new RecordingQuestionnaireMailer();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        $production = $this->givenPublishedQuestionnaireProduction($organizations, $projects, $productions, $questionnaires);

        $performance = Performance::create($production->id(), new DateTimeImmutable('2026-10-10'), '13:00', '15:00', 100, null, null);
        $performance->changeStatus(PerformanceStatus::fromString(PerformanceStatus::FINISHED));
        $performances->save($performance);

        $reservation = Reservation::create($performance->id(), TicketId::generate(), '山田太郎', 'booker@example.com', 4, 1000, null);
        $reservation->checkIn(null);
        $reservations->save($reservation);

        $useCase = $this->buildUseCase($performances, $questionnaires, $reservations, $inviteRecords, $mailer, $productions, $projects, $organizations);
        $useCase->execute($performance->id());

        $this->assertCount(1, $mailer->sentTo);
        $this->assertSame('booker@example.com', $mailer->sentTo[0]);
    }

    public function test_cancelled_and_no_show_reservations_are_excluded(): void
    {
        $performances = new InMemoryPerformanceRepository();
        $questionnaires = new InMemoryQuestionnaireRepository();
        $reservations = new InMemoryReservationRepository();
        $inviteRecords = new InMemoryQuestionnaireInviteRecordRepository();
        $mailer = new RecordingQuestionnaireMailer();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        $production = $this->givenPublishedQuestionnaireProduction($organizations, $projects, $productions, $questionnaires);
        $performance = Performance::create($production->id(), new DateTimeImmutable('2026-10-10'), '13:00', '15:00', 100, null, null);
        $performances->save($performance);

        $cancelled = Reservation::create($performance->id(), TicketId::generate(), 'A', 'cancelled@example.com', 1, 1000, null);
        $cancelled->cancel(null);
        $reservations->save($cancelled);

        $noShow = Reservation::create($performance->id(), TicketId::generate(), 'B', 'noshow@example.com', 1, 1000, null);
        $noShow->markNoShow(null);
        $reservations->save($noShow);

        $useCase = $this->buildUseCase($performances, $questionnaires, $reservations, $inviteRecords, $mailer, $productions, $projects, $organizations);
        $useCase->execute($performance->id());

        $this->assertCount(0, $mailer->sentTo);
    }

    public function test_does_not_send_twice_for_same_performance_and_reservation(): void
    {
        $performances = new InMemoryPerformanceRepository();
        $questionnaires = new InMemoryQuestionnaireRepository();
        $reservations = new InMemoryReservationRepository();
        $inviteRecords = new InMemoryQuestionnaireInviteRecordRepository();
        $mailer = new RecordingQuestionnaireMailer();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        $production = $this->givenPublishedQuestionnaireProduction($organizations, $projects, $productions, $questionnaires);
        $performance = Performance::create($production->id(), new DateTimeImmutable('2026-10-10'), '13:00', '15:00', 100, null, null);
        $performances->save($performance);

        $reservation = Reservation::create($performance->id(), TicketId::generate(), 'A', 'booker@example.com', 1, 1000, null);
        $reservation->checkIn(null);
        $reservations->save($reservation);

        $useCase = $this->buildUseCase($performances, $questionnaires, $reservations, $inviteRecords, $mailer, $productions, $projects, $organizations);
        $useCase->execute($performance->id());
        $useCase->execute($performance->id());

        $this->assertCount(1, $mailer->sentTo);
    }

    public function test_no_questionnaire_means_no_email_and_no_error(): void
    {
        $performances = new InMemoryPerformanceRepository();
        $questionnaires = new InMemoryQuestionnaireRepository();
        $reservations = new InMemoryReservationRepository();
        $inviteRecords = new InMemoryQuestionnaireInviteRecordRepository();
        $mailer = new RecordingQuestionnaireMailer();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);
        $primaryManager = Person::create(1);
        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $productions->save($production);

        $performance = Performance::create($production->id(), new DateTimeImmutable('2026-10-10'), '13:00', '15:00', 100, null, null);
        $performances->save($performance);

        $reservation = Reservation::create($performance->id(), TicketId::generate(), 'A', 'booker@example.com', 1, 1000, null);
        $reservation->checkIn(null);
        $reservations->save($reservation);

        $useCase = $this->buildUseCase($performances, $questionnaires, $reservations, $inviteRecords, $mailer, $productions, $projects, $organizations);
        $useCase->execute($performance->id());

        $this->assertCount(0, $mailer->sentTo);
    }

    public function test_draft_questionnaire_sends_no_email(): void
    {
        $performances = new InMemoryPerformanceRepository();
        $questionnaires = new InMemoryQuestionnaireRepository();
        $reservations = new InMemoryReservationRepository();
        $inviteRecords = new InMemoryQuestionnaireInviteRecordRepository();
        $mailer = new RecordingQuestionnaireMailer();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);
        $primaryManager = Person::create(1);
        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $productions->save($production);

        // DRAFT - never published.
        $questionnaires->save(Questionnaire::create($production->id(), 'アンケート', null, null));

        $performance = Performance::create($production->id(), new DateTimeImmutable('2026-10-10'), '13:00', '15:00', 100, null, null);
        $performances->save($performance);

        $reservation = Reservation::create($performance->id(), TicketId::generate(), 'A', 'booker@example.com', 1, 1000, null);
        $reservation->checkIn(null);
        $reservations->save($reservation);

        $useCase = $this->buildUseCase($performances, $questionnaires, $reservations, $inviteRecords, $mailer, $productions, $projects, $organizations);
        $useCase->execute($performance->id());

        $this->assertCount(0, $mailer->sentTo);
    }
}

final class RecordingQuestionnaireMailer implements QuestionnaireMailerInterface
{
    /** @var string[] */
    public array $sentTo = [];

    public function sendInviteEmail(string $toEmail, string $productionName, string $publicUrl): void
    {
        $this->sentTo[] = $toEmail;
    }
}
