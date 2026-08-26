<?php

declare(strict_types=1);

namespace StageArt\Presentation;

use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Account\ListAccountsUseCase;
use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Infrastructure\WordPress\Notification\WordPressNotificationDispatcher;
use StageArt\Application\Budget\ActivateBudgetUseCase;
use StageArt\Application\Budget\BudgetLineFactory;
use StageArt\Application\Budget\CreateBudgetUseCase;
use StageArt\Application\Budget\GetBudgetUseCase;
use StageArt\Application\Budget\ListBudgetsUseCase;
use StageArt\Application\Budget\UpdateBudgetUseCase;
use StageArt\Application\Expense\ConfirmExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseLineFactory;
use StageArt\Application\Expense\GetExpenseUseCase;
use StageArt\Application\Expense\ListExpensesUseCase;
use StageArt\Application\Expense\UpdateExpenseUseCase;
use StageArt\Application\JournalEntry\ListJournalEntriesUseCase;
use StageArt\Application\JournalEntry\PostJournalEntryUseCase;
use StageArt\Application\Favorite\AddFavoriteUseCase;
use StageArt\Application\Favorite\ListMyFavoritesUseCase;
use StageArt\Application\Favorite\RemoveFavoriteUseCase;
use StageArt\Application\Follow\FollowOrganizationUseCase;
use StageArt\Application\Follow\ListMyFollowsUseCase;
use StageArt\Application\Follow\UnfollowOrganizationUseCase;
use StageArt\Application\JoinKey\DisableJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueProductionJoinKeyUseCase;
use StageArt\Application\JoinKey\ResolveJoinKeyUseCase;
use StageArt\Application\Membership\ApproveMembershipRequestUseCase;
use StageArt\Application\Membership\ListMyMembershipsUseCase;
use StageArt\Application\Membership\ListPendingMembershipRequestsUseCase;
use StageArt\Application\Membership\RejectMembershipRequestUseCase;
use StageArt\Application\Membership\RequestOrganizationMembershipUseCase;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Participant\ApproveParticipantRequestUseCase;
use StageArt\Application\Participant\ListPendingParticipantRequestsUseCase;
use StageArt\Application\Participant\RejectParticipantRequestUseCase;
use StageArt\Application\Participant\RequestProductionParticipationUseCase;
use StageArt\Application\Organization\DeleteOrganizationUseCase;
use StageArt\Application\Organization\GetOrganizationUseCase;
use StageArt\Application\Organization\GetPublicOrganizationBySlugUseCase;
use StageArt\Application\Organization\ListOrganizationsUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OwnerTransferUseCase;
use StageArt\Application\Organization\SearchOrganizationsUseCase;
use StageArt\Application\Production\SearchProductionsUseCase;
use StageArt\Application\Organization\UpdateOrganizationUseCase;
use StageArt\Application\Participant\CancelParticipantUseCase;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\GetParticipantUseCase;
use StageArt\Application\Participant\ListParticipantsUseCase;
use StageArt\Application\Participant\UpdateParticipantUseCase;
use StageArt\Application\Production\ActivateProductionUseCase;
use StageArt\Application\Production\ArchiveProductionUseCase;
use StageArt\Application\Production\CancelProductionUseCase;
use StageArt\Application\Production\ChangePrimaryManagerUseCase;
use StageArt\Application\Production\CompleteProductionUseCase;
use StageArt\Application\Production\CreateProductionUseCase;
use StageArt\Application\Production\GetProductionUseCase;
use StageArt\Application\Production\GetPublicProductionBySlugUseCase;
use StageArt\Application\Production\ListProductionsUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Production\StartProductionPlanningUseCase;
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryUseCase;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesUseCase;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateUseCase;
use StageArt\Application\ProductionSchedule\ListProductionTimetableItemsUseCase;
use StageArt\Application\Project\ArchiveProjectUseCase;
use StageArt\Application\Project\CreateProjectUseCase;
use StageArt\Application\Project\GetProjectUseCase;
use StageArt\Application\Project\ListProjectsUseCase;
use StageArt\Application\Project\UpdateProjectUseCase;
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
use StageArt\Application\Notification\GetPushPreferenceUseCase;
use StageArt\Application\Dashboard\GetMyDashboardUseCase;
use StageArt\Application\Notification\ListNotificationsForProductionUseCase;
use StageArt\Application\Notification\MarkNotificationReadUseCase;
use StageArt\Application\Notification\UpdatePushPreferenceUseCase;
use StageArt\Application\Person\GetCurrentPersonUseCase;
use StageArt\Application\Person\UpdatePersonNameUseCase;
use StageArt\Application\PrintView\GetProductionPrintViewUseCase;
use StageArt\Application\Authentication\AuthenticateWithEmailUseCase;
use StageArt\Application\Authentication\AuthenticateWithGoogleUseCase;
use StageArt\Application\Authentication\LinkGoogleIdentityUseCase;
use StageArt\Application\Authentication\LogoutUseCase;
use StageArt\Application\Authentication\RefreshAccessTokenUseCase;
use StageArt\Application\Authentication\RegisterWithEmailUseCase;
use StageArt\Application\Authentication\RequestPasswordResetUseCase;
use StageArt\Application\Authentication\ResetPasswordUseCase;
use StageArt\Application\Authentication\VerifyEmailUseCase;
use StageArt\Application\UserAccount\ChangePasswordUseCase;
use StageArt\Application\UserAccount\CreateUserAccountUseCase;
use StageArt\Application\UserAccount\RegisterEmailCredentialUseCase;
use StageArt\Application\UserAccount\RequestEmailVerificationUseCase;
use StageArt\Infrastructure\WordPress\Persistence\WordPressAccountRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressBudgetRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressEmailCredentialRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressEmailVerificationTokenRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressExpenseRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressJournalEntryRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPasswordResetTokenRepository;
use StageArt\Infrastructure\Authentication\GoogleIdTokenVerifier;
use StageArt\Infrastructure\Authentication\JwtAccessTokenIssuer;
use StageArt\Infrastructure\Authentication\JwtAccessTokenVerifier;
use StageArt\Infrastructure\WordPress\Authentication\CurrentUserResolver;
use StageArt\Infrastructure\WordPress\Authentication\WordPressAuthMailer;
use StageArt\Infrastructure\WordPress\Authentication\WordPressUserProvisioner;
use StageArt\Infrastructure\WordPress\Persistence\WordPressExternalIdentityRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressRefreshTokenRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressMembershipRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressFavoriteRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressJoinKeyRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressOrganizationFollowRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressOrganizationRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressParticipantRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPersonRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressProductionDelegateRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressProductionRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressProjectRepository;
use StageArt\Infrastructure\Pdf\DompdfRenderer;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPushPreferenceRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressRehearsalAttendanceRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressRehearsalRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressScheduleCommentRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTimetableItemRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTimetableRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressNotificationReadStateRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTimetableVersionPublishedNotificationRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTransactionManager;
use StageArt\Infrastructure\WordPress\Persistence\WordPressUserAccountRepository;
use StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader;
use StageArt\Presentation\Admin\OrganizationAdminPage;
use StageArt\Presentation\Admin\ProjectAdminPage;
use StageArt\Presentation\Rest\AccountRestController;
use StageArt\Presentation\Rest\AuthenticationRestController;
use StageArt\Presentation\Rest\BudgetRestController;
use StageArt\Presentation\Rest\DashboardRestController;
use StageArt\Presentation\Rest\ExpenseRestController;
use StageArt\Presentation\Rest\FavoriteRestController;
use StageArt\Presentation\Rest\JoinKeyRestController;
use StageArt\Presentation\Rest\JournalEntryRestController;
use StageArt\Presentation\Rest\MeRestController;
use StageArt\Presentation\Rest\MembershipRestController;
use StageArt\Presentation\Rest\ProductionAccountingRestController;
use StageArt\Presentation\Print\PrintViewHtmlRenderer;
use StageArt\Presentation\Rest\NotificationRestController;
use StageArt\Presentation\Rest\PrintViewRestController;
use StageArt\Presentation\Rest\OrganizationRestController;
use StageArt\Presentation\Rest\ParticipantRestController;
use StageArt\Presentation\Rest\ParticipationRequestRestController;
use StageArt\Presentation\Rest\ProductionDelegateRestController;
use StageArt\Presentation\Rest\ProductionRestController;
use StageArt\Presentation\Rest\ProjectRestController;
use StageArt\Presentation\Rest\PushPreferenceRestController;
use StageArt\Presentation\Rest\RehearsalAttendanceRestController;
use StageArt\Presentation\Rest\RehearsalRestController;
use StageArt\Presentation\Rest\ScheduleCommentRestController;
use StageArt\Presentation\Rest\TimetableItemRestController;
use StageArt\Presentation\Rest\TimetableRestController;
use StageArt\Presentation\Rest\TimetableVersionRestController;
use StageArt\Presentation\Rest\UserAccountRestController;

final class Plugin
{
    public function boot(): void
    {
        add_action('init', static function (): void {
            load_plugin_textdomain('stageart', false, dirname(plugin_basename(STAGEART_PLUGIN_FILE)) . '/languages');
        });

        SchemaUpgrader::maybeUpgrade();

        global $wpdb;

        $organizations       = new WordPressOrganizationRepository($wpdb);
        $organizationFollows = new WordPressOrganizationFollowRepository($wpdb);
        $joinKeys            = new WordPressJoinKeyRepository($wpdb);
        $favorites           = new WordPressFavoriteRepository($wpdb);
        $people              = new WordPressPersonRepository($wpdb);
        $memberships         = new WordPressMembershipRepository($wpdb);
        $projects            = new WordPressProjectRepository($wpdb);
        $userAccounts        = new WordPressUserAccountRepository($wpdb);
        $emailCredentials    = new WordPressEmailCredentialRepository($wpdb);
        // Phase 2 (StageArt Authentication): now consumed by
        // AuthenticateWithGoogleUseCase/LinkGoogleIdentityUseCase below.
        $externalIdentities  = new WordPressExternalIdentityRepository($wpdb);
        $refreshTokens       = new WordPressRefreshTokenRepository($wpdb);
        $passwordResetTokens = new WordPressPasswordResetTokenRepository($wpdb);
        $emailVerificationTokens = new WordPressEmailVerificationTokenRepository($wpdb);
        $productions         = new WordPressProductionRepository($wpdb);
        $productionDelegates = new WordPressProductionDelegateRepository($wpdb);
        $participants        = new WordPressParticipantRepository($wpdb);
        $rehearsals           = new WordPressRehearsalRepository($wpdb);
        $rehearsalAttendances = new WordPressRehearsalAttendanceRepository($wpdb);
        $scheduleComments     = new WordPressScheduleCommentRepository($wpdb);
        $timetables           = new WordPressTimetableRepository($wpdb);
        $timetableItems       = new WordPressTimetableItemRepository($wpdb);
        $timetableVersionPublishedNotifications = new WordPressTimetableVersionPublishedNotificationRepository($wpdb);
        $notificationReadStates = new WordPressNotificationReadStateRepository($wpdb);
        $pushPreferences      = new WordPressPushPreferenceRepository($wpdb);
        $accounts             = new WordPressAccountRepository($wpdb);
        $budgets              = new WordPressBudgetRepository($wpdb);
        $journalEntries       = new WordPressJournalEntryRepository($wpdb);
        $expenses             = new WordPressExpenseRepository($wpdb);
        $transactions        = new WordPressTransactionManager($wpdb);

        $authorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($authorization, $productionDelegates, $participants);
        $issueProductionJoinKey = new IssueProductionJoinKeyUseCase($productions, $joinKeys, $productionAuthorization);
        $resolveJoinKey = new ResolveJoinKeyUseCase($joinKeys, $organizations, $productions);
        $disableJoinKey = new DisableJoinKeyUseCase($joinKeys, $productions, $authorization, $productionAuthorization);
        $searchProductions = new SearchProductionsUseCase($productions, $projects, $organizations);
        $productionOrganizationResolver = new ProductionOrganizationResolver($projects);
        $membershipContract = new CoreMembershipAdapter($participants, $productions, $people, $productionAuthorization);
        $productionContextContract = new CoreProductionContextAdapter($productions, $productionOrganizationResolver);
        $identityContract = new CoreIdentityAdapter($people);
        $authorizationContract = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);
        $organizationContextContract = new CoreOrganizationContextAdapter($organizations);
        $notificationDispatcher = new WordPressNotificationDispatcher();
        $notificationContract = new CoreNotificationAdapter($notificationDispatcher);
        $nextTimetableVersionResolver = new NextTimetableVersionResolver($timetables);
        $budgetLineFactory = new BudgetLineFactory($accounts);
        $expenseLineFactory = new ExpenseLineFactory($accounts);

        $createUserAccount        = new CreateUserAccountUseCase($people, $userAccounts, $transactions);
        $registerEmailCredential  = new RegisterEmailCredentialUseCase(
            $people,
            $userAccounts,
            $emailCredentials,
            $transactions
        );

        // Phase 2 (StageArt Authentication): both secrets are read from
        // wp-config.php constants, never committed to source control
        // (see this Phase's Google Cloud Console setup guide). An unset
        // Google Web Client ID makes GoogleIdTokenVerifier fail closed
        // (every token's audience check fails) rather than the whole
        // plugin refusing to boot - see GoogleIdTokenVerifier's own
        // docblock.
        $googleWebClientId = defined('STAGEART_GOOGLE_WEB_CLIENT_ID') ? STAGEART_GOOGLE_WEB_CLIENT_ID : '';
        $jwtSecret = defined('STAGEART_JWT_SECRET') ? STAGEART_JWT_SECRET : '';

        $googleIdTokenVerifier = new GoogleIdTokenVerifier($googleWebClientId);
        $accessTokenIssuer = new JwtAccessTokenIssuer($jwtSecret);
        $accessTokenVerifier = new JwtAccessTokenVerifier($jwtSecret);
        $wordPressUserProvisioner = new WordPressUserProvisioner();
        $currentUserResolver = new CurrentUserResolver($accessTokenVerifier, $people);
        // A sibling path to the WordPress docroot's own /stageart-test/
        // symlink (see this Phase's server-layout investigation), not
        // under it - the exported Web confirmation page is a separate
        // static site, never routed through WordPress's own .htaccess.
        $emailVerificationBaseUrl = defined('STAGEART_EMAIL_VERIFICATION_BASE_URL')
            ? STAGEART_EMAIL_VERIFICATION_BASE_URL
            : 'https://dev-stageart.hatakiti.com/verify-app';
        $authMailer = new WordPressAuthMailer($emailVerificationBaseUrl);

        $authenticateWithGoogle = new AuthenticateWithGoogleUseCase(
            $googleIdTokenVerifier,
            $externalIdentities,
            $userAccounts,
            $people,
            $refreshTokens,
            $accessTokenIssuer,
            $wordPressUserProvisioner,
            $transactions
        );
        $registerWithEmail = new RegisterWithEmailUseCase(
            $emailCredentials,
            $people,
            $userAccounts,
            $refreshTokens,
            $emailVerificationTokens,
            $accessTokenIssuer,
            $wordPressUserProvisioner,
            $transactions,
            $authMailer
        );
        $authenticateWithEmail = new AuthenticateWithEmailUseCase(
            $emailCredentials,
            $userAccounts,
            $people,
            $refreshTokens,
            $accessTokenIssuer,
            $transactions
        );
        $refreshAccessToken = new RefreshAccessTokenUseCase($refreshTokens, $userAccounts, $people, $accessTokenIssuer);
        $logout = new LogoutUseCase($refreshTokens);
        $linkGoogleIdentity = new LinkGoogleIdentityUseCase(
            $googleIdTokenVerifier,
            $people,
            $userAccounts,
            $externalIdentities,
            $transactions
        );
        $requestPasswordReset = new RequestPasswordResetUseCase(
            $emailCredentials,
            $passwordResetTokens,
            $authMailer,
            $transactions
        );
        $resetPassword = new ResetPasswordUseCase($passwordResetTokens, $emailCredentials, $refreshTokens, $transactions);
        $verifyEmail = new VerifyEmailUseCase($emailVerificationTokens, $emailCredentials, $transactions);
        $changePassword = new ChangePasswordUseCase($people, $userAccounts, $emailCredentials, $transactions);
        $requestEmailVerification = new RequestEmailVerificationUseCase(
            $people,
            $userAccounts,
            $emailCredentials,
            $emailVerificationTokens,
            $authMailer
        );

        $createOrganization = new CreateOrganizationUseCase(
            $organizations,
            $people,
            $memberships,
            $transactions
        );
        $getOrganization    = new GetOrganizationUseCase($organizations, $organizationFollows, $authorization);
        $getPublicOrganizationBySlug = new GetPublicOrganizationBySlugUseCase($organizations, $projects, $productions);
        $listOrganizations  = new ListOrganizationsUseCase($organizations, $memberships, $authorization);
        $updateOrganization = new UpdateOrganizationUseCase($organizations, $authorization);
        $deleteOrganization = new DeleteOrganizationUseCase($organizations, $authorization);
        $ownerTransfer      = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, $transactions);
        $followOrganization   = new FollowOrganizationUseCase($organizations, $organizationFollows, $authorization);
        $unfollowOrganization = new UnfollowOrganizationUseCase($organizationFollows, $authorization);
        $listMyFollows         = new ListMyFollowsUseCase($organizationFollows, $organizations, $authorization);

        // StageArt Web β版: Join Key + Membership Request/Approval (団体・
        // 公演への所属申請フロー). ProductionAuthorizationService/$productions
        // are declared further below (Production Scope wiring); the Join
        // Key Use Cases that need them are instantiated after that point -
        // see issueProductionJoinKey/disableJoinKey below.
        $issueOrganizationJoinKey = new IssueOrganizationJoinKeyUseCase($organizations, $joinKeys, $authorization);
        $requestOrganizationMembership = new RequestOrganizationMembershipUseCase($organizations, $memberships, $joinKeys, $authorization);
        $approveMembershipRequest = new ApproveMembershipRequestUseCase($memberships, $people, $authorization);
        $rejectMembershipRequest = new RejectMembershipRequestUseCase($memberships, $people, $authorization);
        $listPendingMembershipRequests = new ListPendingMembershipRequestsUseCase($memberships, $people, $authorization);
        $listMyMemberships = new ListMyMembershipsUseCase($memberships, $organizations, $authorization);
        $searchOrganizations = new SearchOrganizationsUseCase($organizations);
        $addFavorite = new AddFavoriteUseCase($favorites, $organizations, $productions, $authorization);
        $removeFavorite = new RemoveFavoriteUseCase($favorites, $authorization);
        $listMyFavorites = new ListMyFavoritesUseCase($favorites, $organizations, $productions, $projects, $authorization);

        $createProject = new CreateProjectUseCase($projects, $authorization);
        $getProject     = new GetProjectUseCase($projects, $authorization);
        $listProjects   = new ListProjectsUseCase($projects, $memberships, $authorization);
        $updateProject  = new UpdateProjectUseCase($projects, $authorization);
        $archiveProject = new ArchiveProjectUseCase($projects, $authorization);

        $createProduction = new CreateProductionUseCase(
            $productions,
            $projects,
            $memberships,
            $userAccounts,
            $authorization,
            $transactions
        );
        $getProduction = new GetProductionUseCase($productions, $productionAuthorization);
        $getPublicProductionBySlug = new GetPublicProductionBySlugUseCase($productions, $projects, $organizations);
        $listProductions = new ListProductionsUseCase($productions, $productionDelegates, $productionAuthorization);
        $updateProduction = new UpdateProductionUseCase($productions, $productionAuthorization);
        $startProductionPlanning = new StartProductionPlanningUseCase($productions, $productionAuthorization);
        $activateProduction = new ActivateProductionUseCase($productions, $productionAuthorization);
        $completeProduction = new CompleteProductionUseCase($productions, $productionAuthorization);
        $archiveProduction = new ArchiveProductionUseCase($productions, $productionAuthorization);
        $cancelProduction = new CancelProductionUseCase($productions, $productionAuthorization);
        $changePrimaryManager = new ChangePrimaryManagerUseCase(
            $productions,
            $projects,
            $memberships,
            $userAccounts,
            $productionAuthorization,
            $transactions
        );

        $createProductionDelegate = new CreateProductionDelegateUseCase(
            $productions,
            $productionDelegates,
            $people,
            $productionAuthorization,
            $transactions
        );
        $listProductionDelegates = new ListProductionDelegatesUseCase($productionDelegates, $productions, $productionAuthorization);
        $updateProductionDelegate = new UpdateProductionDelegateUseCase($productionDelegates, $productions, $productionAuthorization);
        $deleteProductionDelegate = new DeleteProductionDelegateUseCase($productionDelegates, $productions, $productionAuthorization);

        $createParticipant = new CreateParticipantUseCase(
            $productions,
            $participants,
            $people,
            $organizations,
            $productionAuthorization,
            $transactions
        );
        $getParticipant = new GetParticipantUseCase($participants, $productions, $productionAuthorization);
        $listParticipants = new ListParticipantsUseCase($participants, $productions, $productionAuthorization);
        $updateParticipant = new UpdateParticipantUseCase($participants, $productions, $productionAuthorization);
        $cancelParticipant = new CancelParticipantUseCase($participants, $productions, $productionAuthorization);

        $createRehearsal = new CreateRehearsalUseCase(
            $productionContextContract,
            $rehearsals,
            $rehearsalAttendances,
            $membershipContract,
            $identityContract,
            $authorizationContract,
            $transactions
        );
        $getRehearsal = new GetRehearsalUseCase($rehearsals, $productionContextContract, $identityContract, $membershipContract);
        $listRehearsals = new ListRehearsalsUseCase($rehearsals, $productionContextContract, $identityContract, $membershipContract);
        $updateRehearsal = new UpdateRehearsalUseCase($rehearsals, $productionContextContract, $identityContract, $authorizationContract);
        $confirmRehearsal = new ConfirmRehearsalUseCase(
            $rehearsals,
            $productionContextContract,
            $rehearsalAttendances,
            $membershipContract,
            $identityContract,
            $authorizationContract,
            $transactions
        );
        $activateRehearsal = new ActivateRehearsalUseCase($rehearsals, $productionContextContract, $identityContract, $authorizationContract);
        $completeRehearsal = new CompleteRehearsalUseCase($rehearsals, $productionContextContract, $identityContract, $authorizationContract);
        $cancelRehearsal = new CancelRehearsalUseCase($rehearsals, $productionContextContract, $identityContract, $authorizationContract);

        $listRehearsalAttendances = new ListRehearsalAttendancesUseCase(
            $rehearsalAttendances,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $membershipContract
        );
        $getRehearsalAttendance = new GetRehearsalAttendanceUseCase(
            $rehearsalAttendances,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $membershipContract
        );
        $respondRehearsalAttendance = new RespondRehearsalAttendanceUseCase($rehearsalAttendances, $identityContract);
        $recordActualRehearsalAttendanceStatus = new RecordActualRehearsalAttendanceStatusUseCase(
            $rehearsalAttendances,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $authorizationContract
        );

        $createScheduleComment = new CreateScheduleCommentUseCase(
            $scheduleComments,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $membershipContract
        );
        $listScheduleComments = new ListScheduleCommentsUseCase(
            $scheduleComments,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $membershipContract
        );
        $updateScheduleComment = new UpdateScheduleCommentUseCase($scheduleComments, $identityContract);
        $deleteScheduleComment = new DeleteScheduleCommentUseCase(
            $scheduleComments,
            $rehearsals,
            $productionContextContract,
            $timetables,
            $timetableItems,
            $identityContract,
            $authorizationContract
        );
        $createTimetableItemScheduleComment = new CreateTimetableItemScheduleCommentUseCase(
            $scheduleComments,
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $membershipContract
        );
        $listTimetableItemScheduleComments = new ListTimetableItemScheduleCommentsUseCase(
            $scheduleComments,
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $membershipContract
        );

        $timetableItemTargetValidator = new TimetableItemTargetValidator($membershipContract);

        $getTimetable = new GetTimetableUseCase($timetables, $rehearsals, $productionContextContract, $identityContract, $membershipContract);
        $getDraftTimetable = new GetDraftTimetableUseCase($timetables, $rehearsals, $productionContextContract, $identityContract, $membershipContract);
        $listTimetableVersions = new ListTimetableVersionsUseCase($timetables, $rehearsals, $productionContextContract, $identityContract, $membershipContract);
        $createNewTimetableVersion = new CreateNewTimetableVersionUseCase(
            $rehearsals,
            $productionContextContract,
            $timetables,
            $timetableItems,
            $nextTimetableVersionResolver,
            $identityContract,
            $authorizationContract,
            $transactions
        );
        $publishTimetableVersion = new PublishTimetableVersionUseCase(
            $timetables,
            $rehearsals,
            $productionContextContract,
            $timetableVersionPublishedNotifications,
            $membershipContract,
            $notificationContract,
            $identityContract,
            $authorizationContract,
            $transactions
        );
        $listTimetableItems = new ListTimetableItemsUseCase(
            $rehearsals,
            $productionContextContract,
            $timetables,
            $timetableItems,
            $identityContract,
            $membershipContract
        );
        $listDraftTimetableItems = new ListDraftTimetableItemsUseCase(
            $rehearsals,
            $productionContextContract,
            $timetables,
            $timetableItems,
            $identityContract,
            $membershipContract
        );
        $listProductionTimetableItems = new ListProductionTimetableItemsUseCase(
            $productions,
            $rehearsals,
            $timetables,
            $timetableItems,
            $productionAuthorization
        );
        $listNotificationsForProduction = new ListNotificationsForProductionUseCase(
            $productions,
            $timetableVersionPublishedNotifications,
            $notificationReadStates,
            $productionAuthorization
        );
        $markNotificationRead = new MarkNotificationReadUseCase(
            $timetableVersionPublishedNotifications,
            $notificationReadStates,
            $productions,
            $productionAuthorization
        );
        $getMyDashboard = new GetMyDashboardUseCase(
            $productions,
            $productionDelegates,
            $participants,
            $rehearsals,
            $rehearsalAttendances,
            $timetableVersionPublishedNotifications,
            $notificationReadStates,
            $organizationFollows,
            $projects,
            $organizations,
            $productionAuthorization
        );
        $getPushPreference = new GetPushPreferenceUseCase($pushPreferences, $productionAuthorization);
        $updatePushPreference = new UpdatePushPreferenceUseCase($pushPreferences, $productionAuthorization);
        $getCurrentPerson = new GetCurrentPersonUseCase($authorization, $userAccounts, $emailCredentials);
        $updatePersonName = new UpdatePersonNameUseCase($people);
        $getProductionPrintView = new GetProductionPrintViewUseCase(
            $productions,
            $rehearsals,
            $timetables,
            $timetableItems,
            $productionAuthorization
        );
        $createTimetableItem = new CreateTimetableItemUseCase(
            $rehearsals,
            $productionContextContract,
            $timetables,
            $timetableItems,
            $timetableItemTargetValidator,
            $nextTimetableVersionResolver,
            $identityContract,
            $authorizationContract,
            $transactions
        );
        $getTimetableItem = new GetTimetableItemUseCase(
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $membershipContract
        );
        $updateTimetableItem = new UpdateTimetableItemUseCase(
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContextContract,
            $timetableItemTargetValidator,
            $identityContract,
            $authorizationContract,
            $transactions
        );
        $deleteTimetableItem = new DeleteTimetableItemUseCase(
            $timetableItems,
            $timetables,
            $rehearsals,
            $productionContextContract,
            $identityContract,
            $authorizationContract
        );

        // Phase 6.0 Accounting Foundation.
        $createAccount = new CreateAccountUseCase($accounts, $organizations, $authorization);
        $listAccounts = new ListAccountsUseCase($accounts, $organizations, $authorization);

        $createBudget = new CreateBudgetUseCase(
            $budgets,
            $productionContextContract,
            $identityContract,
            $authorizationContract,
            $budgetLineFactory,
            $transactions
        );
        $updateBudget = new UpdateBudgetUseCase(
            $budgets,
            $productionContextContract,
            $identityContract,
            $authorizationContract,
            $budgetLineFactory,
            $transactions
        );
        $getBudget = new GetBudgetUseCase($budgets, $identityContract, $authorizationContract);
        $listBudgets = new ListBudgetsUseCase($budgets, $productionContextContract, $identityContract, $authorizationContract);
        $activateBudget = new ActivateBudgetUseCase($budgets, $identityContract, $authorizationContract, $transactions);

        $createExpense = new CreateExpenseUseCase(
            $expenses,
            $productionContextContract,
            $membershipContract,
            $identityContract,
            $expenseLineFactory,
            $transactions
        );
        $updateExpense = new UpdateExpenseUseCase(
            $expenses,
            $productionContextContract,
            $identityContract,
            $authorizationContract,
            $expenseLineFactory,
            $transactions
        );
        $confirmExpense = new ConfirmExpenseUseCase(
            $expenses,
            $productionContextContract,
            $accounts,
            $journalEntries,
            $identityContract,
            $authorizationContract,
            $transactions
        );
        $getExpense = new GetExpenseUseCase($expenses, $identityContract, $membershipContract);
        $listExpenses = new ListExpensesUseCase($expenses, $productionContextContract, $membershipContract, $identityContract);

        $listJournalEntries = new ListJournalEntriesUseCase($journalEntries, $productionContextContract, $identityContract, $authorizationContract);
        $postJournalEntry = new PostJournalEntryUseCase(
            $journalEntries,
            $productionContextContract,
            $authorization,
            $authorizationContract,
            $transactions
        );

        $getProductionAccountingSummary = new GetProductionAccountingSummaryUseCase(
            $budgets,
            $journalEntries,
            $accounts,
            $productionContextContract,
            $membershipContract,
            $identityContract
        );

        $organizationRestController = new OrganizationRestController(
            $createOrganization,
            $getOrganization,
            $getPublicOrganizationBySlug,
            $listOrganizations,
            $updateOrganization,
            $deleteOrganization,
            $ownerTransfer,
            $followOrganization,
            $unfollowOrganization,
            $searchOrganizations
        );

        $projectRestController = new ProjectRestController(
            $createProject,
            $getProject,
            $listProjects,
            $updateProject,
            $archiveProject
        );

        $userAccountRestController = new UserAccountRestController(
            $createUserAccount,
            $registerEmailCredential,
            $linkGoogleIdentity,
            $changePassword,
            $requestEmailVerification
        );
        $authenticationRestController = new AuthenticationRestController(
            $authenticateWithGoogle,
            $registerWithEmail,
            $authenticateWithEmail,
            $refreshAccessToken,
            $logout,
            $requestPasswordReset,
            $resetPassword,
            $verifyEmail
        );

        $productionRestController = new ProductionRestController(
            $createProduction,
            $getProduction,
            $getPublicProductionBySlug,
            $listProductions,
            $updateProduction,
            $changePrimaryManager,
            $startProductionPlanning,
            $activateProduction,
            $completeProduction,
            $archiveProduction,
            $cancelProduction,
            $searchProductions
        );

        $productionDelegateRestController = new ProductionDelegateRestController(
            $createProductionDelegate,
            $listProductionDelegates,
            $updateProductionDelegate,
            $deleteProductionDelegate
        );

        $participantRestController = new ParticipantRestController(
            $createParticipant,
            $getParticipant,
            $listParticipants,
            $updateParticipant,
            $cancelParticipant
        );

        $requestProductionParticipation = new RequestProductionParticipationUseCase($productions, $participants, $joinKeys, $productionAuthorization);
        $approveParticipantRequest = new ApproveParticipantRequestUseCase($participants, $productions, $people, $productionAuthorization);
        $rejectParticipantRequest = new RejectParticipantRequestUseCase($participants, $productions, $people, $productionAuthorization);
        $listPendingParticipantRequests = new ListPendingParticipantRequestsUseCase($participants, $productions, $people, $productionAuthorization);

        $participationRequestRestController = new ParticipationRequestRestController(
            $requestProductionParticipation,
            $approveParticipantRequest,
            $rejectParticipantRequest,
            $listPendingParticipantRequests
        );

        $rehearsalRestController = new RehearsalRestController(
            $createRehearsal,
            $getRehearsal,
            $listRehearsals,
            $updateRehearsal,
            $confirmRehearsal,
            $activateRehearsal,
            $completeRehearsal,
            $cancelRehearsal
        );

        $rehearsalAttendanceRestController = new RehearsalAttendanceRestController(
            $listRehearsalAttendances,
            $getRehearsalAttendance,
            $respondRehearsalAttendance,
            $recordActualRehearsalAttendanceStatus
        );

        $scheduleCommentRestController = new ScheduleCommentRestController(
            $createScheduleComment,
            $listScheduleComments,
            $updateScheduleComment,
            $deleteScheduleComment,
            $createTimetableItemScheduleComment,
            $listTimetableItemScheduleComments
        );

        $timetableRestController = new TimetableRestController(
            $getTimetable,
            $getDraftTimetable,
            $listTimetableItems,
            $listDraftTimetableItems,
            $listProductionTimetableItems
        );

        $timetableVersionRestController = new TimetableVersionRestController(
            $listTimetableVersions,
            $createNewTimetableVersion,
            $publishTimetableVersion
        );

        $timetableItemRestController = new TimetableItemRestController(
            $createTimetableItem,
            $getTimetableItem,
            $updateTimetableItem,
            $deleteTimetableItem
        );

        $notificationRestController = new NotificationRestController($listNotificationsForProduction, $markNotificationRead);
        $dashboardRestController = new DashboardRestController($getMyDashboard);

        $pushPreferenceRestController = new PushPreferenceRestController($getPushPreference, $updatePushPreference);

        $meRestController = new MeRestController($getCurrentPerson, $updatePersonName, $listMyFollows);

        $joinKeyRestController = new JoinKeyRestController(
            $issueOrganizationJoinKey,
            $issueProductionJoinKey,
            $resolveJoinKey,
            $disableJoinKey,
            $joinKeys
        );

        $membershipRestController = new MembershipRestController(
            $requestOrganizationMembership,
            $approveMembershipRequest,
            $rejectMembershipRequest,
            $listPendingMembershipRequests,
            $listMyMemberships
        );

        $favoriteRestController = new FavoriteRestController($addFavorite, $removeFavorite, $listMyFavorites);

        $printViewRestController = new PrintViewRestController(
            $getProductionPrintView,
            new PrintViewHtmlRenderer(),
            new DompdfRenderer()
        );

        $accountRestController = new AccountRestController($createAccount, $listAccounts);
        $budgetRestController = new BudgetRestController(
            $createBudget,
            $updateBudget,
            $getBudget,
            $listBudgets,
            $activateBudget
        );
        $expenseRestController = new ExpenseRestController(
            $createExpense,
            $updateExpense,
            $confirmExpense,
            $getExpense,
            $listExpenses
        );
        $journalEntryRestController = new JournalEntryRestController($listJournalEntries, $postJournalEntry);
        $productionAccountingRestController = new ProductionAccountingRestController($getProductionAccountingSummary);

        add_action('rest_api_init', [$organizationRestController, 'register_routes']);
        add_action('rest_api_init', [$projectRestController, 'register_routes']);
        add_action('rest_api_init', [$userAccountRestController, 'register_routes']);
        add_action('rest_api_init', [$authenticationRestController, 'register_routes']);
        add_action('rest_api_init', [$productionRestController, 'register_routes']);
        add_action('rest_api_init', [$productionDelegateRestController, 'register_routes']);
        add_action('rest_api_init', [$participantRestController, 'register_routes']);
        add_action('rest_api_init', [$participationRequestRestController, 'register_routes']);
        add_action('rest_api_init', [$rehearsalRestController, 'register_routes']);
        add_action('rest_api_init', [$rehearsalAttendanceRestController, 'register_routes']);
        add_action('rest_api_init', [$scheduleCommentRestController, 'register_routes']);
        add_action('rest_api_init', [$timetableRestController, 'register_routes']);
        add_action('rest_api_init', [$timetableVersionRestController, 'register_routes']);
        add_action('rest_api_init', [$timetableItemRestController, 'register_routes']);
        add_action('rest_api_init', [$notificationRestController, 'register_routes']);
        add_action('rest_api_init', [$dashboardRestController, 'register_routes']);
        add_action('rest_api_init', [$pushPreferenceRestController, 'register_routes']);
        add_action('rest_api_init', [$meRestController, 'register_routes']);
        add_action('rest_api_init', [$joinKeyRestController, 'register_routes']);
        add_action('rest_api_init', [$membershipRestController, 'register_routes']);
        add_action('rest_api_init', [$favoriteRestController, 'register_routes']);
        add_action('rest_api_init', [$printViewRestController, 'register_routes']);
        add_action('rest_api_init', [$accountRestController, 'register_routes']);
        add_action('rest_api_init', [$budgetRestController, 'register_routes']);
        add_action('rest_api_init', [$expenseRestController, 'register_routes']);
        add_action('rest_api_init', [$journalEntryRestController, 'register_routes']);
        add_action('rest_api_init', [$productionAccountingRestController, 'register_routes']);

        // Phase 2 (StageArt Authentication): registers the StageArt
        // Bearer Access Token as an additional way WordPress resolves
        // "who is the current user" - the same extension point
        // Application Password Basic Auth already uses internally, so
        // both coexist without either needing to change (see
        // CurrentUserResolver's own docblock and this Phase's design
        // report §1). Every existing REST Controller's
        // is_user_logged_in()/get_current_user_id() call keeps working
        // completely unchanged.
        add_filter('determine_current_user', [$currentUserResolver, 'resolve']);

        (new OrganizationAdminPage(
            $createOrganization,
            $getOrganization,
            $listOrganizations,
            $updateOrganization,
            $deleteOrganization
        ))->register();

        (new ProjectAdminPage(
            $createProject,
            $getProject,
            $listProjects,
            $updateProject,
            $archiveProject,
            $listOrganizations
        ))->register();
    }
}
