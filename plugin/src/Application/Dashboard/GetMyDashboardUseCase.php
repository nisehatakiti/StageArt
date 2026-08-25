<?php

declare(strict_types=1);

namespace StageArt\Application\Dashboard;

use DateTimeImmutable;
use StageArt\Application\Notification\NotificationResult;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Follow\OrganizationFollowRepositoryInterface;
use StageArt\Domain\Notification\NotificationReadStateRepositoryInterface;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Project\ProjectRepositoryInterface;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalStatus;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

/**
 * Phase 7.3: a Person-centric Read Model, not "the Dashboard screen" -
 * see this Phase's report §39. It answers two questions independently,
 * matching DashboardPolicy.md's two Business Rules exactly, and does
 * not conflate them:
 *
 * 1. Upcoming Rehearsals: driven purely by RehearsalAttendance (the
 *    Blueprint-confirmed "参加対象" signal - see Membership.md /
 *    Participant.md), never by Production membership. A Person with no
 *    RehearsalAttendance row for a Rehearsal never sees it here, even
 *    if they are a Production Participant or Delegate.
 *
 * 2. Notifications: the existing per-Production Broadcast model
 *    (ListNotificationsForProductionUseCase's "Shared Visibility
 *    Principle") is reused unchanged and merely aggregated across every
 *    Production the Person is broadly involved in (PrimaryManager,
 *    active Delegate, or active Person-Participant). This does NOT
 *    achieve DashboardPolicy.md's literal "Recipientが自分のみ" rule -
 *    see this Phase's report §07/§16 for why per-recipient Notification
 *    was not implemented this Phase.
 */
final class GetMyDashboardUseCase
{
    private const UPCOMING_REHEARSAL_LIMIT = 50;
    private const NOTIFICATION_LIMIT = 50;
    private const FOLLOWED_ORGANIZATIONS_FEED_LIMIT = 20;

    /** Rehearsal.md's terminal statuses - a completed/cancelled Rehearsal is never "今後の予定". */
    private const EXCLUDED_REHEARSAL_STATUSES = [RehearsalStatus::COMPLETED, RehearsalStatus::CANCELLED];

    private ProductionRepositoryInterface $productions;
    private ProductionDelegateRepositoryInterface $delegates;
    private ParticipantRepositoryInterface $participants;
    private RehearsalRepositoryInterface $rehearsals;
    private RehearsalAttendanceRepositoryInterface $attendances;
    private TimetableVersionPublishedNotificationRepositoryInterface $notifications;
    private NotificationReadStateRepositoryInterface $readStates;
    private OrganizationFollowRepositoryInterface $follows;
    private ProjectRepositoryInterface $projects;
    private OrganizationRepositoryInterface $organizations;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionDelegateRepositoryInterface $delegates,
        ParticipantRepositoryInterface $participants,
        RehearsalRepositoryInterface $rehearsals,
        RehearsalAttendanceRepositoryInterface $attendances,
        TimetableVersionPublishedNotificationRepositoryInterface $notifications,
        NotificationReadStateRepositoryInterface $readStates,
        OrganizationFollowRepositoryInterface $follows,
        ProjectRepositoryInterface $projects,
        OrganizationRepositoryInterface $organizations,
        ProductionAuthorizationService $authorization
    ) {
        $this->productions = $productions;
        $this->delegates = $delegates;
        $this->participants = $participants;
        $this->rehearsals = $rehearsals;
        $this->attendances = $attendances;
        $this->notifications = $notifications;
        $this->readStates = $readStates;
        $this->follows = $follows;
        $this->projects = $projects;
        $this->organizations = $organizations;
        $this->authorization = $authorization;
    }

    public function execute(GetMyDashboardQuery $query): MyDashboardResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new DashboardAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        return new MyDashboardResult(
            $this->resolveUpcomingRehearsals($requester->id()),
            $this->resolveNotifications($requester->id()),
            $this->resolveFollowedOrganizationsFeed($requester->id())
        );
    }

    /**
     * docs/04-DomainModel/Follow.md's "フォロー中の新着": the most recently
     * published Productions belonging to Organizations the Person
     * actively follows, resolved live from current Facts (see
     * FollowedOrganizationFeedItemResult's own docblock for why this is
     * not a stored Feed Item).
     *
     * @return FollowedOrganizationFeedItemResult[]
     */
    private function resolveFollowedOrganizationsFeed(PersonId $personId): array
    {
        $follows = $this->follows->findActiveByPersonId($personId);

        if ($follows === []) {
            return [];
        }

        $organizationIds = array_map(static fn ($follow) => $follow->organizationId(), $follows);

        $projects = $this->projects->findByOrganizationIds($organizationIds);

        if ($projects === []) {
            return [];
        }

        /** @var array<string, \StageArt\Domain\Organization\OrganizationId> $organizationIdByProjectId */
        $organizationIdByProjectId = [];
        foreach ($projects as $project) {
            $organizationIdByProjectId[$project->id()->toString()] = $project->organizationId();
        }

        $productions = array_filter(
            $this->productions->findByProjectIds(array_map(
                static fn ($project) => $project->id(),
                $projects
            )),
            static fn (Production $production): bool => $production->publishedAt() !== null
        );

        if ($productions === []) {
            return [];
        }

        usort(
            $productions,
            static fn (Production $a, Production $b): int => $b->publishedAt() <=> $a->publishedAt()
        );

        $productions = array_slice($productions, 0, self::FOLLOWED_ORGANIZATIONS_FEED_LIMIT);

        $organizations = $this->organizations->findByIds(array_values($organizationIdByProjectId));

        /** @var array<string, Organization> $organizationsById */
        $organizationsById = [];
        foreach ($organizations as $organization) {
            $organizationsById[$organization->id()->toString()] = $organization;
        }

        $results = [];
        foreach ($productions as $production) {
            $organizationId = $organizationIdByProjectId[$production->projectId()->toString()] ?? null;
            $organization = $organizationId ? ($organizationsById[$organizationId->toString()] ?? null) : null;

            if (! $organization) {
                continue;
            }

            $results[] = FollowedOrganizationFeedItemResult::fromDomain($production, $organization);
        }

        return $results;
    }

    /**
     * @return UpcomingRehearsalResult[]
     */
    private function resolveUpcomingRehearsals(PersonId $personId): array
    {
        $attendances = $this->attendances->findUpcomingByPersonId(
            $personId,
            new DateTimeImmutable(),
            self::EXCLUDED_REHEARSAL_STATUSES,
            self::UPCOMING_REHEARSAL_LIMIT
        );

        if ($attendances === []) {
            return [];
        }

        $rehearsals = $this->rehearsals->findByIds(array_map(
            static fn ($attendance) => $attendance->rehearsalId(),
            $attendances
        ));

        /** @var array<string, Rehearsal> $rehearsalsById */
        $rehearsalsById = [];
        foreach ($rehearsals as $rehearsal) {
            $rehearsalsById[$rehearsal->id()->toString()] = $rehearsal;
        }

        $productions = $this->productions->findByIds(array_map(
            static fn (Rehearsal $rehearsal): ProductionId => $rehearsal->productionId(),
            $rehearsals
        ));

        /** @var array<string, Production> $productionsById */
        $productionsById = [];
        foreach ($productions as $production) {
            $productionsById[$production->id()->toString()] = $production;
        }

        $results = [];

        foreach ($attendances as $attendance) {
            $rehearsal = $rehearsalsById[$attendance->rehearsalId()->toString()] ?? null;

            if (! $rehearsal) {
                continue;
            }

            // A Rehearsal keeps its superseded Phase 1 record after
            // reaching CONFIRMED (see RehearsalAttendancePhase::
            // forRehearsalStatus()'s docblock) - only the phase matching
            // the Rehearsal's current status counts as "current".
            if (! $attendance->phase()->equals(RehearsalAttendancePhase::forRehearsalStatus($rehearsal->status()))) {
                continue;
            }

            $production = $productionsById[$rehearsal->productionId()->toString()] ?? null;

            if (! $production) {
                continue;
            }

            $results[] = UpcomingRehearsalResult::fromDomain($attendance, $rehearsal, $production);
        }

        return $results;
    }

    /**
     * @return NotificationResult[]
     */
    private function resolveNotifications(PersonId $personId): array
    {
        $productionIds = $this->resolveInvolvedProductionIds($personId);

        if ($productionIds === []) {
            return [];
        }

        $notifications = array_slice(
            $this->notifications->findByProductionIds($productionIds),
            0,
            self::NOTIFICATION_LIMIT
        );

        $notificationIds = array_map(
            static fn ($notification) => $notification->id()->toString(),
            $notifications
        );
        $readStates = $this->readStates->findByPersonAndNotificationIds($personId, $notificationIds);

        return array_map(
            static fn ($notification) => NotificationResult::fromTimetableVersionPublished(
                $notification,
                isset($readStates[$notification->id()->toString()])
            ),
            $notifications
        );
    }

    /**
     * Broad "which Productions is this Person involved in" resolution:
     * PrimaryManager ∪ active ProductionDelegate ∪ active Person-
     * Participant. Deliberately NOT ListProductionsUseCase (Manager/
     * Delegate only - see this Phase's report §06 for why GET
     * /productions was not widened or reused here) and NOT
     * ProductionAuthorizationService::isProductionMember() (that method
     * takes one Production at a time; this resolves the whole set with a
     * bounded number of bulk queries).
     *
     * @return ProductionId[]
     */
    private function resolveInvolvedProductionIds(PersonId $personId): array
    {
        $ids = [];

        foreach ($this->productions->findByPrimaryManagerPersonId($personId) as $production) {
            $ids[$production->id()->toString()] = $production->id();
        }

        foreach ($this->delegates->findByPersonId($personId) as $delegate) {
            if ($delegate->isActive()) {
                $ids[$delegate->productionId()->toString()] = $delegate->productionId();
            }
        }

        $activeStatus = ParticipantStatus::active();

        foreach ($this->participants->findBySubject(ParticipantSubjectType::person(), $personId->toString()) as $participant) {
            if ($participant->status()->equals($activeStatus)) {
                $ids[$participant->productionId()->toString()] = $participant->productionId();
            }
        }

        return array_values($ids);
    }
}
