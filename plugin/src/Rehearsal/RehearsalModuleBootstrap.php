<?php

declare(strict_types=1);

namespace StageArt\Rehearsal;

use StageArt\Application\Dashboard\UpcomingRehearsalProviderInterface;
use StageArt\Application\PrintView\GetProductionPrintViewUseCase;
use StageArt\Application\ProductionSchedule\ListProductionTimetableItemsUseCase;
use StageArt\Application\Rehearsal\ActivateRehearsalUseCase;
use StageArt\Application\Rehearsal\CancelRehearsalUseCase;
use StageArt\Application\Rehearsal\CompleteRehearsalUseCase;
use StageArt\Application\Rehearsal\ConfirmRehearsalUseCase;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\GetRehearsalUseCase;
use StageArt\Application\Rehearsal\ListRehearsalsUseCase;
use StageArt\Application\Rehearsal\UpdateRehearsalUseCase;
use StageArt\Application\RehearsalAttendance\GetRehearsalAttendanceUseCase;
use StageArt\Application\RehearsalAttendance\ListRehearsalAttendancesUseCase;
use StageArt\Application\RehearsalAttendance\RecordActualRehearsalAttendanceStatusUseCase;
use StageArt\Application\RehearsalAttendance\RespondRehearsalAttendanceUseCase;
use StageArt\Application\ScheduleComment\CreateScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\CreateTimetableItemScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\DeleteScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\ListScheduleCommentsUseCase;
use StageArt\Application\ScheduleComment\ListTimetableItemScheduleCommentsUseCase;
use StageArt\Application\ScheduleComment\UpdateScheduleCommentUseCase;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\Timetable\CreateNewTimetableVersionUseCase;
use StageArt\Application\Timetable\GetDraftTimetableUseCase;
use StageArt\Application\Timetable\GetTimetableUseCase;
use StageArt\Application\Timetable\ListTimetableVersionsUseCase;
use StageArt\Application\Timetable\NextTimetableVersionResolver;
use StageArt\Application\Timetable\PublishTimetableVersionUseCase;
use StageArt\Application\TimetableItem\CreateTimetableItemUseCase;
use StageArt\Application\TimetableItem\DeleteTimetableItemUseCase;
use StageArt\Application\TimetableItem\GetTimetableItemUseCase;
use StageArt\Application\TimetableItem\ListDraftTimetableItemsUseCase;
use StageArt\Application\TimetableItem\ListTimetableItemsUseCase;
use StageArt\Application\TimetableItem\TimetableItemTargetValidator;
use StageArt\Application\TimetableItem\UpdateTimetableItemUseCase;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Infrastructure\Pdf\DompdfRenderer;
use StageArt\Presentation\Print\PrintViewHtmlRenderer;
use StageArt\Presentation\Rest\PrintViewRestController;
use StageArt\Presentation\Rest\RehearsalAttendanceRestController;
use StageArt\Presentation\Rest\RehearsalRestController;
use StageArt\Presentation\Rest\ScheduleCommentRestController;
use StageArt\Presentation\Rest\TimetableItemRestController;
use StageArt\Presentation\Rest\TimetableRestController;
use StageArt\Presentation\Rest\TimetableVersionRestController;

/**
 * StageArt Core/Module Architecture Phase 3 (docs/architecture/
 * WordPressPluginModuleBoundary.md §8): the Rehearsal Module's entire
 * wiring responsibility - Repository-backed UseCase construction and
 * REST Controller registration - consolidated into one independent
 * unit. This is the concrete answer to "can Rehearsal's registration be
 * pulled out of Plugin.php's own boot sequence and called from
 * somewhere else instead (a future separate StageArt Rehearsal Plugin's
 * own entry point)": every constructor argument below is either a Core
 * Contract (`StageArt\Core\Contract\*`) or one of Rehearsal's own
 * Repository *interfaces* - never a concrete `Infrastructure\WordPress\*`
 * class, never `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService`/any other Core-internal type.
 * `RehearsalModuleBootstrapIsolationTest` proves this concretely by
 * constructing this class with hand-written Fakes for every Contract
 * and in-memory Fakes for every Repository - no `Infrastructure\
 * WordPress\*` class is ever touched.
 *
 * `Presentation\Plugin::boot()` is today's only caller (it builds the
 * Core Contract Adapters and Rehearsal's own
 * `Infrastructure\WordPress\Persistence\*` repositories, then hands
 * them here) - but nothing about this class's own shape assumes that;
 * a future separate Plugin's activation entry point could construct and
 * call it identically.
 *
 * `GetProductionPrintViewUseCase`/`ListProductionTimetableItemsUseCase`
 * are included here (not just the `Rehearsal`/`RehearsalAttendance`/
 * `Timetable`/`TimetableItem`/`ScheduleComment` namespaces) because
 * their own REST Controllers (`PrintViewRestController`,
 * `TimetableRestController`) are Rehearsal-owned per
 * `docs/architecture/CoreModuleArchitecture.md` §10 - Module ownership
 * follows the REST Controller's declared owner, not which Application
 * sub-namespace a UseCase happens to live under.
 *
 * `GetMyDashboardUseCase` (Core's own cross-Module aggregation UseCase)
 * is NOT constructed here - it is Core's own code, wired by
 * `Plugin.php` directly - but its former direct dependency on
 * `RehearsalRepositoryInterface`/`RehearsalAttendanceRepositoryInterface`
 * (a disclosed Core -> Module Domain coupling, the reverse of every
 * other direction this Bootstrap enforces) is closed as of Phase 4 §1
 * via `upcomingRehearsalProvider()` below - Core depends on
 * `Application\Dashboard\UpcomingRehearsalProviderInterface` (a Port
 * Core itself defines), and this Bootstrap is what supplies the one
 * real implementation.
 */
final class RehearsalModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;

    private UpcomingRehearsalProviderInterface $upcomingRehearsalProvider;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        RehearsalAttendanceRepositoryInterface $rehearsalAttendances,
        ScheduleCommentRepositoryInterface $scheduleComments,
        TimetableRepositoryInterface $timetables,
        TimetableItemRepositoryInterface $timetableItems,
        TimetableVersionPublishedNotificationRepositoryInterface $timetableVersionPublishedNotifications,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        MembershipContract $membership,
        NotificationContract $notification,
        TransactionManagerInterface $transactions
    ) {
        $nextTimetableVersionResolver = new NextTimetableVersionResolver($timetables);
        $timetableItemTargetValidator = new TimetableItemTargetValidator($membership);

        $createRehearsal = new CreateRehearsalUseCase(
            $productionContext,
            $rehearsals,
            $rehearsalAttendances,
            $membership,
            $identity,
            $authorization,
            $transactions
        );
        $getRehearsal = new GetRehearsalUseCase($rehearsals, $productionContext, $identity, $membership);
        $listRehearsals = new ListRehearsalsUseCase($rehearsals, $productionContext, $identity, $membership);
        $updateRehearsal = new UpdateRehearsalUseCase($rehearsals, $productionContext, $identity, $authorization);
        $confirmRehearsal = new ConfirmRehearsalUseCase(
            $rehearsals,
            $productionContext,
            $rehearsalAttendances,
            $membership,
            $identity,
            $authorization,
            $transactions
        );
        $activateRehearsal = new ActivateRehearsalUseCase($rehearsals, $productionContext, $identity, $authorization);
        $completeRehearsal = new CompleteRehearsalUseCase($rehearsals, $productionContext, $identity, $authorization);
        $cancelRehearsal = new CancelRehearsalUseCase($rehearsals, $productionContext, $identity, $authorization);

        $listRehearsalAttendances = new ListRehearsalAttendancesUseCase(
            $rehearsalAttendances,
            $rehearsals,
            $productionContext,
            $identity,
            $membership
        );
        $getRehearsalAttendance = new GetRehearsalAttendanceUseCase(
            $rehearsalAttendances,
            $rehearsals,
            $productionContext,
            $identity,
            $membership
        );
        $respondRehearsalAttendance = new RespondRehearsalAttendanceUseCase($rehearsalAttendances, $identity);
        $recordActualRehearsalAttendanceStatus = new RecordActualRehearsalAttendanceStatusUseCase(
            $rehearsalAttendances,
            $rehearsals,
            $productionContext,
            $identity,
            $authorization
        );

        $createScheduleComment = new CreateScheduleCommentUseCase(
            $scheduleComments,
            $rehearsals,
            $productionContext,
            $identity,
            $membership
        );
        $listScheduleComments = new ListScheduleCommentsUseCase(
            $scheduleComments,
            $rehearsals,
            $productionContext,
            $identity,
            $membership
        );
        $updateScheduleComment = new UpdateScheduleCommentUseCase($scheduleComments, $identity);
        $deleteScheduleComment = new DeleteScheduleCommentUseCase(
            $scheduleComments,
            $rehearsals,
            $productionContext,
            $timetables,
            $timetableItems,
            $identity,
            $authorization
        );
        $createTimetableItemScheduleComment = new CreateTimetableItemScheduleCommentUseCase(
            $scheduleComments,
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContext,
            $identity,
            $membership
        );
        $listTimetableItemScheduleComments = new ListTimetableItemScheduleCommentsUseCase(
            $scheduleComments,
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContext,
            $identity,
            $membership
        );

        $getTimetable = new GetTimetableUseCase($timetables, $rehearsals, $productionContext, $identity, $membership);
        $getDraftTimetable = new GetDraftTimetableUseCase($timetables, $rehearsals, $productionContext, $identity, $membership);
        $listTimetableVersions = new ListTimetableVersionsUseCase($timetables, $rehearsals, $productionContext, $identity, $membership);
        $createNewTimetableVersion = new CreateNewTimetableVersionUseCase(
            $rehearsals,
            $productionContext,
            $timetables,
            $timetableItems,
            $nextTimetableVersionResolver,
            $identity,
            $authorization,
            $transactions
        );
        $publishTimetableVersion = new PublishTimetableVersionUseCase(
            $timetables,
            $rehearsals,
            $productionContext,
            $timetableVersionPublishedNotifications,
            $membership,
            $notification,
            $identity,
            $authorization,
            $transactions
        );
        $listTimetableItems = new ListTimetableItemsUseCase(
            $rehearsals,
            $productionContext,
            $timetables,
            $timetableItems,
            $identity,
            $membership
        );
        $listDraftTimetableItems = new ListDraftTimetableItemsUseCase(
            $rehearsals,
            $productionContext,
            $timetables,
            $timetableItems,
            $identity,
            $membership
        );
        $listProductionTimetableItems = new ListProductionTimetableItemsUseCase(
            $productionContext,
            $rehearsals,
            $timetables,
            $timetableItems,
            $identity,
            $membership
        );
        $createTimetableItem = new CreateTimetableItemUseCase(
            $rehearsals,
            $productionContext,
            $timetables,
            $timetableItems,
            $timetableItemTargetValidator,
            $nextTimetableVersionResolver,
            $identity,
            $authorization,
            $transactions
        );
        $getTimetableItem = new GetTimetableItemUseCase(
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContext,
            $identity,
            $membership
        );
        $updateTimetableItem = new UpdateTimetableItemUseCase(
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContext,
            $timetableItemTargetValidator,
            $identity,
            $authorization,
            $transactions
        );
        $deleteTimetableItem = new DeleteTimetableItemUseCase(
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContext,
            $identity,
            $authorization
        );

        $getProductionPrintView = new GetProductionPrintViewUseCase(
            $productionContext,
            $rehearsals,
            $timetables,
            $timetableItems,
            $identity,
            $membership
        );

        $this->restControllers = [
            new RehearsalRestController(
                $createRehearsal,
                $getRehearsal,
                $listRehearsals,
                $updateRehearsal,
                $confirmRehearsal,
                $activateRehearsal,
                $completeRehearsal,
                $cancelRehearsal
            ),
            new RehearsalAttendanceRestController(
                $listRehearsalAttendances,
                $getRehearsalAttendance,
                $respondRehearsalAttendance,
                $recordActualRehearsalAttendanceStatus
            ),
            new ScheduleCommentRestController(
                $createScheduleComment,
                $listScheduleComments,
                $updateScheduleComment,
                $deleteScheduleComment,
                $createTimetableItemScheduleComment,
                $listTimetableItemScheduleComments
            ),
            new TimetableRestController(
                $getTimetable,
                $getDraftTimetable,
                $listTimetableItems,
                $listDraftTimetableItems,
                $listProductionTimetableItems
            ),
            new TimetableVersionRestController(
                $listTimetableVersions,
                $createNewTimetableVersion,
                $publishTimetableVersion
            ),
            new TimetableItemRestController(
                $createTimetableItem,
                $getTimetableItem,
                $updateTimetableItem,
                $deleteTimetableItem
            ),
            new PrintViewRestController(
                $getProductionPrintView,
                new PrintViewHtmlRenderer(),
                new DompdfRenderer()
            ),
        ];

        $this->upcomingRehearsalProvider = new RehearsalUpcomingRehearsalProvider(
            $rehearsals,
            $rehearsalAttendances,
            $productionContext
        );
    }

    /**
     * Every REST Controller this Module owns, each with its own public
     * `register_routes()` method - the caller loops these onto
     * `add_action('rest_api_init', ...)` exactly as it would for any
     * other Controller. Route paths/methods are unchanged from before
     * this Bootstrap existed - this class only relocated *where* the
     * Controllers are constructed, not what they register.
     *
     * @return array<int, object>
     */
    public function restControllers(): array
    {
        return $this->restControllers;
    }

    /**
     * StageArt Core/Module Architecture Phase 4 §1: the Rehearsal
     * Module's own implementation of Core's
     * `Application\Dashboard\UpcomingRehearsalProviderInterface` Port -
     * the concrete answer to "how does Core get Dashboard data out of
     * Rehearsal without depending on Rehearsal's Repository interfaces
     * directly". `Presentation\Plugin::boot()` passes this straight into
     * `GetMyDashboardUseCase`'s own constructor.
     */
    public function upcomingRehearsalProvider(): UpcomingRehearsalProviderInterface
    {
        return $this->upcomingRehearsalProvider;
    }
}
