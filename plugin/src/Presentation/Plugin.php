<?php

declare(strict_types=1);

namespace StageArt\Presentation;

use StageArt\Application\Admin\CreateAdminConsoleAccountUseCase;
use StageArt\Application\Notification\GetNotificationEmailSettingsUseCase;
use StageArt\Application\Notification\InAppNotificationAdapter;
use StageArt\Application\Notification\ListMyNotificationsUseCase;
use StageArt\Application\Notification\MarkMyNotificationReadUseCase;
use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Application\Notification\NotificationEmailSeeder;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Notification\RequestNotificationEmailChangeUseCase;
use StageArt\Application\Notification\VerifyNotificationEmailChangeUseCase;
use StageArt\Application\UserAccount\BlockUserAccountsUseCase;
use StageArt\Application\UserAccount\DeleteUserAccountsUseCase;
use StageArt\Application\UserAccount\ListAllUserAccountsUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Application\Rehearsal\SendRehearsalReminderCommand;
use StageArt\Infrastructure\WordPress\Notification\WordPressEmailNotificationAdapter;
use StageArt\Infrastructure\WordPress\Notification\WordPressNotificationDispatcher;
use StageArt\Infrastructure\WordPress\Persistence\WordPressNotificationRepository;
use StageArt\Infrastructure\WordPress\Rehearsal\WordPressRehearsalReminderScheduler;
use StageArt\Accounting\AccountingModuleBootstrap;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\CheckIn\CheckInModuleBootstrap;
use StageArt\Performance\PerformanceModuleBootstrap;
use StageArt\Questionnaire\QuestionnaireModuleBootstrap;
use StageArt\Reservation\ReservationModuleBootstrap;
use StageArt\MemberPerformanceSummary\MemberPerformanceSummaryModuleBootstrap;
use StageArt\Settlement\SettlementModuleBootstrap;
use StageArt\Ticket\TicketModuleBootstrap;
use StageArt\Rehearsal\RehearsalModuleBootstrap;
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
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesUseCase;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateUseCase;
use StageArt\Application\Project\ArchiveProjectUseCase;
use StageArt\Application\Project\CreateProjectUseCase;
use StageArt\Application\Project\GetProjectUseCase;
use StageArt\Application\Project\ListProjectsUseCase;
use StageArt\Application\Project\UpdateProjectUseCase;
use StageArt\Application\Notification\GetPushPreferenceUseCase;
use StageArt\Application\Dashboard\GetMyDashboardUseCase;
use StageArt\Application\Notification\ListNotificationsForProductionUseCase;
use StageArt\Application\Notification\MarkNotificationReadUseCase;
use StageArt\Application\Notification\UpdatePushPreferenceUseCase;
use StageArt\Application\Person\GetCurrentPersonUseCase;
use StageArt\Application\Person\UpdatePersonNameUseCase;
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
use StageArt\Infrastructure\WordPress\Persistence\WordPressCheckInRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressSettlementRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressWalkUpIdempotencyStore;
use StageArt\Infrastructure\WordPress\Persistence\WordPressEmailCredentialRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressEmailVerificationTokenRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressExpenseRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressJournalEntryRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPasswordResetTokenRepository;
use StageArt\Infrastructure\Authentication\GoogleIdTokenVerifier;
use StageArt\Infrastructure\Authentication\JwtAccessTokenIssuer;
use StageArt\Infrastructure\Authentication\JwtAccessTokenVerifier;
use StageArt\Infrastructure\WordPress\Authentication\CurrentUserResolver;
use StageArt\Infrastructure\WordPress\Authentication\WordPressAdminConsoleAccountProvisioner;
use StageArt\Infrastructure\WordPress\Authentication\WordPressAuthMailer;
use StageArt\Infrastructure\WordPress\Authentication\WordPressUserLookup;
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
use StageArt\Infrastructure\WordPress\Persistence\WordPressIssuedTicketRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPerformanceRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressQuestionRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressQuestionnaireInviteRecordRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressQuestionnaireRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressQuestionnaireResponseRepository;
use StageArt\Infrastructure\WordPress\Questionnaire\WordPressQuestionnaireMailer;
use StageArt\Infrastructure\WordPress\Persistence\WordPressReservationRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTicketRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressProjectRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPushPreferenceRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressNotificationEmailRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressNotificationEmailChangeRequestRepository;
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
use StageArt\Presentation\Admin\AccountManagementAdminPage;
use StageArt\Presentation\Admin\AdminConsoleAccountAdminPage;
use StageArt\Presentation\Admin\OrganizationAdminPage;
use StageArt\Presentation\Admin\ProjectAdminPage;
use StageArt\Presentation\Rest\AuthenticationRestController;
use StageArt\Presentation\Rest\DashboardRestController;
use StageArt\Presentation\Rest\FavoriteRestController;
use StageArt\Presentation\Rest\JoinKeyRestController;
use StageArt\Presentation\Rest\MeRestController;
use StageArt\Presentation\Rest\MembershipRestController;
use StageArt\Presentation\Rest\NotificationRestController;
use StageArt\Presentation\Rest\OrganizationRestController;
use StageArt\Presentation\Rest\ParticipantRestController;
use StageArt\Presentation\Rest\ParticipationRequestRestController;
use StageArt\Presentation\Rest\ProductionDelegateRestController;
use StageArt\Presentation\Rest\ProductionRestController;
use StageArt\Presentation\Rest\ProjectRestController;
use StageArt\Presentation\Rest\PushPreferenceRestController;
use StageArt\Presentation\Rest\NotificationEmailRestController;
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
        $performances         = new WordPressPerformanceRepository($wpdb);
        $tickets              = new WordPressTicketRepository($wpdb);
        $reservations         = new WordPressReservationRepository($wpdb);
        $issuedTickets        = new WordPressIssuedTicketRepository($wpdb);
        $questionnaires        = new WordPressQuestionnaireRepository($wpdb);
        $questionnaireQuestions = new WordPressQuestionRepository($wpdb);
        $questionnaireResponses = new WordPressQuestionnaireResponseRepository($wpdb);
        $questionnaireInviteRecords = new WordPressQuestionnaireInviteRecordRepository($wpdb);
        $checkIns             = new WordPressCheckInRepository($wpdb);
        $walkUpIdempotencyStore = new WordPressWalkUpIdempotencyStore($wpdb);
        $settlements          = new WordPressSettlementRepository($wpdb);
        $rehearsalAttendances = new WordPressRehearsalAttendanceRepository($wpdb);
        $scheduleComments     = new WordPressScheduleCommentRepository($wpdb);
        $timetables           = new WordPressTimetableRepository($wpdb);
        $timetableItems       = new WordPressTimetableItemRepository($wpdb);
        $timetableVersionPublishedNotifications = new WordPressTimetableVersionPublishedNotificationRepository($wpdb);
        $notificationReadStates = new WordPressNotificationReadStateRepository($wpdb);
        $pushPreferences      = new WordPressPushPreferenceRepository($wpdb);
        $notifications        = new WordPressNotificationRepository($wpdb);
        $notificationEmails   = new WordPressNotificationEmailRepository($wpdb);
        $notificationEmailChangeRequests = new WordPressNotificationEmailChangeRequestRepository($wpdb);
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
        $productionContextContract = new CoreProductionContextAdapter($productions, $productionOrganizationResolver, $organizations);
        $identityContract = new CoreIdentityAdapter($people);
        $authorizationContract = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);
        $organizationContextContract = new CoreOrganizationContextAdapter($organizations);

        // Notification基盤実装 phase: In-App (highest priority per this
        // phase's instruction) and Email delivery Adapters, registered
        // with the dispatcher below. Push has no real provider decided
        // yet - see WordPressNotificationDispatcher's own docblock for
        // why the `do_action('stageart_notification', ...)` hook stays
        // as that channel's event-emission point instead of a fake
        // implementation. WordPressUserLookup is stateless and
        // constructed again (harmlessly) at its own original call site
        // further down for ListAllUserAccountsUseCase.
        $notificationInAppAdapter = new InAppNotificationAdapter($notifications);
        $personEmailResolver = new PersonEmailResolver($people, $userAccounts, $emailCredentials, new WordPressUserLookup(), $notificationEmails);
        $notificationEmailAdapter = new WordPressEmailNotificationAdapter($personEmailResolver);
        $notificationDispatcher = new WordPressNotificationDispatcher([$notificationInAppAdapter, $notificationEmailAdapter]);
        $notificationContract = new CoreNotificationAdapter($notificationDispatcher);
        $rehearsalReminderScheduler = new WordPressRehearsalReminderScheduler();
        // Google認証ユーザーのEmail通知先対応 phase: shared by both
        // AuthenticateWithGoogleUseCase and LinkGoogleIdentityUseCase
        // below - see NotificationEmailSeeder's own docblock.
        $notificationEmailSeeder = new NotificationEmailSeeder($notificationEmails, $userAccounts, $emailCredentials);

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
            $transactions,
            $notificationEmailSeeder
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
            $transactions,
            $notificationEmailSeeder
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

        // 通知用Email確認・変更機能: Settings screen's own read/write
        // Use Cases - reuses $productionAuthorization (the same "resolve
        // my own Person" path GetPushPreferenceUseCase/
        // UpdatePushPreferenceUseCase already use), $personEmailResolver
        // (now the single resolution point for both delivery AND
        // display, see PersonEmailResolver::resolveWithSource()'s own
        // docblock), and $authMailer (a new interface method on the same
        // Port, see AuthMailerInterface's own docblock) - no new
        // dependency kind is introduced.
        $getNotificationEmailSettings = new GetNotificationEmailSettingsUseCase(
            $productionAuthorization,
            $personEmailResolver,
            $notificationEmailChangeRequests
        );
        $requestNotificationEmailChange = new RequestNotificationEmailChangeUseCase(
            $productionAuthorization,
            $personEmailResolver,
            $notificationEmailChangeRequests,
            $authMailer,
            $transactions
        );
        $verifyNotificationEmailChange = new VerifyNotificationEmailChangeUseCase(
            $notificationEmailChangeRequests,
            $notificationEmails,
            $transactions
        );

        // StageArt Admin Console V1 (docs/architecture/StageArtAdminConsole.md):
        // Account Management + Admin Account creation - every Use Case here
        // reuses the same Repositories/Use Cases already wired above
        // ($userAccounts/$people/$emailCredentials/$transactions/
        // $requestPasswordReset); nothing about general authentication is
        // duplicated, only the two new WordPress-specific dependencies
        // (WordPressUserLookup, WordPressAdminConsoleAccountProvisioner)
        // are new.
        $wordPressUserLookup = new WordPressUserLookup();
        $listAllUserAccounts = new ListAllUserAccountsUseCase($userAccounts, $people, $emailCredentials, $wordPressUserLookup);
        $blockUserAccounts = new BlockUserAccountsUseCase($userAccounts, $transactions);
        $deleteUserAccounts = new DeleteUserAccountsUseCase($userAccounts, $transactions);
        $adminConsoleAccountProvisioner = new WordPressAdminConsoleAccountProvisioner();
        $createAdminConsoleAccount = new CreateAdminConsoleAccountUseCase($adminConsoleAccountProvisioner);

        $createOrganization = new CreateOrganizationUseCase(
            $organizations,
            $people,
            $memberships,
            $accounts,
            $journalEntries,
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
        $updateProduction = new UpdateProductionUseCase($productions, $productionAuthorization, $performances, $transactions);
        $activateProduction = new ActivateProductionUseCase($productions, $productionAuthorization);
        $completeProduction = new CompleteProductionUseCase(
            $productions,
            $productionAuthorization,
            new ProductionSettlementCalculator($performances, $reservations, $tickets),
            $settlements,
            $performances,
            $reservations,
            $transactions
        );
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
        $listProductionDelegates = new ListProductionDelegatesUseCase($productionDelegates, $productions, $people, $productionAuthorization);
        $updateProductionDelegate = new UpdateProductionDelegateUseCase($productionDelegates, $productions, $people, $productionAuthorization);
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

        // StageArt Core/Module Architecture Phase 3: Rehearsal Module's
        // entire own wiring (UseCase construction + REST Controller
        // construction) is consolidated into RehearsalModuleBootstrap -
        // see that class's own docblock. Only Core Contracts and
        // Rehearsal's own Repository interfaces cross this boundary.
        $rehearsalModule = new RehearsalModuleBootstrap(
            $rehearsals,
            $rehearsalAttendances,
            $scheduleComments,
            $timetables,
            $timetableItems,
            $timetableVersionPublishedNotifications,
            $productionContextContract,
            $identityContract,
            $authorizationContract,
            $membershipContract,
            $notificationContract,
            $rehearsalReminderScheduler,
            $transactions
        );

        // Phase 7 (Rehearsal仕様整合) §12: the sole Cron hook registration
        // in this plugin - WordPressRehearsalReminderScheduler schedules
        // this event, SendRehearsalReminderUseCase is what actually runs
        // when it fires.
        add_action(WordPressRehearsalReminderScheduler::HOOK, static function (string $rehearsalId) use ($rehearsalModule): void {
            $rehearsalModule->sendRehearsalReminder()->execute(new SendRehearsalReminderCommand($rehearsalId));
        });

        // アンケート実装指示書: Questionnaire Module's entire own wiring,
        // consolidated into QuestionnaireModuleBootstrap - see that
        // class's own docblock. Constructed before PerformanceModuleBootstrap
        // below so its PerformanceFinishedListenerContract implementation
        // can be handed in - see PerformanceFinishedListenerContract's own
        // docblock for why Performance depends only on that generic
        // Contract, never on this Module directly.
        $publicSiteBaseUrl = defined('STAGEART_PUBLIC_SITE_BASE_URL')
            ? STAGEART_PUBLIC_SITE_BASE_URL
            : 'https://dummy.stageart.top';
        $questionnaireMailer = new WordPressQuestionnaireMailer();
        $questionnaireModule = new QuestionnaireModuleBootstrap(
            $questionnaires,
            $questionnaireQuestions,
            $questionnaireResponses,
            $questionnaireInviteRecords,
            $performances,
            $reservations,
            $productionContextContract,
            $identityContract,
            $authorizationContract,
            $questionnaireMailer,
            $publicSiteBaseUrl
        );

        // StageArt Core/Module Architecture Phase 2 Performance基盤:
        // Performance Module's entire own wiring, consolidated into
        // PerformanceModuleBootstrap - see that class's own docblock.
        // Only Core Contracts and Performance's own Repository interface
        // cross this boundary, matching RehearsalModuleBootstrap's
        // precedent above.
        $performanceModule = new PerformanceModuleBootstrap(
            $performances,
            $productionContextContract,
            $identityContract,
            $authorizationContract,
            $membershipContract,
            $questionnaireModule->performanceFinishedListener()
        );

        // StageArt Core/Module Architecture Phase 3 Ticket/Reservation
        // 基盤: Ticket Module's own wiring, consolidated into
        // TicketModuleBootstrap - see that class's own docblock.
        $ticketModule = new TicketModuleBootstrap(
            $tickets,
            $productions,
            $performances,
            $productionContextContract,
            $identityContract,
            $authorizationContract,
            $membershipContract
        );

        // Reservation Module's own wiring, kept separate from Ticket's
        // per instruction §26 - see ReservationModuleBootstrap's own
        // docblock.
        $reservationModule = new ReservationModuleBootstrap(
            $reservations,
            $issuedTickets,
            $tickets,
            $performances,
            $productionContextContract,
            $identityContract,
            $authorizationContract,
            $transactions
        );

        $listNotificationsForProduction = new ListNotificationsForProductionUseCase(
            $productionContextContract,
            $timetableVersionPublishedNotifications,
            $notificationReadStates,
            $identityContract,
            $membershipContract
        );
        $markNotificationRead = new MarkNotificationReadUseCase(
            $timetableVersionPublishedNotifications,
            $notificationReadStates,
            $productionContextContract,
            $identityContract,
            $membershipContract
        );
        $listMyNotifications = new ListMyNotificationsUseCase($notifications, $identityContract);
        $markMyNotificationRead = new MarkMyNotificationReadUseCase($notifications, $identityContract);
        $getMyDashboard = new GetMyDashboardUseCase(
            $productions,
            $productionDelegates,
            $participants,
            $rehearsalModule->upcomingRehearsalProvider(),
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

        // StageArt Core/Module Architecture Phase 3 (continued):
        // Accounting Module's entire own wiring (UseCase construction +
        // REST Controller construction) is consolidated into
        // AccountingModuleBootstrap - see that class's own docblock.
        // Only Core Contracts and Accounting's own Repository
        // interfaces cross this boundary.
        $accountingModule = new AccountingModuleBootstrap(
            $accounts,
            $budgets,
            $expenses,
            $journalEntries,
            $productionContextContract,
            $organizationContextContract,
            $identityContract,
            $authorizationContract,
            $membershipContract,
            $transactions
        );

        // StageArt Core/Module Architecture Phase 4 (Check-in/精算/
        // 会計連携): Check-in Module's own wiring, mirroring every other
        // Module Bootstrap above - see CheckInModuleBootstrap's own
        // docblock.
        $checkInModule = new CheckInModuleBootstrap(
            $reservations,
            $performances,
            $tickets,
            $issuedTickets,
            $checkIns,
            $accounts,
            $journalEntries,
            $productionContextContract,
            $organizationContextContract,
            $identityContract,
            $authorizationContract,
            $membershipContract,
            $walkUpIdempotencyStore,
            $transactions
        );

        // Settlement Module's own wiring - see SettlementModuleBootstrap's
        // own docblock, including why CompleteProductionUseCase above
        // constructs its own separate ProductionSettlementCalculator
        // rather than sharing this Bootstrap's instance.
        $settlementModule = new SettlementModuleBootstrap(
            $productions,
            $performances,
            $reservations,
            $tickets,
            $settlements,
            $membershipContract,
            $people,
            $identityContract,
            $authorizationContract,
            $transactions
        );

        // Phase 5 (Production運営UI §9): Member Performance Summary
        // Module's own wiring - see MemberPerformanceSummaryModuleBootstrap's
        // own docblock.
        $memberPerformanceSummaryModule = new MemberPerformanceSummaryModuleBootstrap(
            $productions,
            $rehearsals,
            $rehearsalAttendances,
            $performances,
            $reservations,
            $tickets,
            $membershipContract,
            $people,
            $identityContract,
            $authorizationContract
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

        $notificationRestController = new NotificationRestController(
            $listNotificationsForProduction,
            $markNotificationRead,
            $listMyNotifications,
            $markMyNotificationRead
        );
        $notificationEmailRestController = new NotificationEmailRestController(
            $getNotificationEmailSettings,
            $requestNotificationEmailChange,
            $verifyNotificationEmailChange
        );
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

        add_action('rest_api_init', [$organizationRestController, 'register_routes']);
        add_action('rest_api_init', [$projectRestController, 'register_routes']);
        add_action('rest_api_init', [$userAccountRestController, 'register_routes']);
        add_action('rest_api_init', [$authenticationRestController, 'register_routes']);
        add_action('rest_api_init', [$productionRestController, 'register_routes']);
        add_action('rest_api_init', [$productionDelegateRestController, 'register_routes']);
        add_action('rest_api_init', [$participantRestController, 'register_routes']);
        add_action('rest_api_init', [$participationRequestRestController, 'register_routes']);

        // StageArt Core/Module Architecture Phase 3: every Rehearsal
        // Module REST Controller (Rehearsal/RehearsalAttendance/
        // ScheduleComment/Timetable/TimetableVersion/TimetableItem/
        // PrintView) is constructed inside RehearsalModuleBootstrap -
        // registered identically to every other Controller here, just
        // sourced from the Bootstrap instead of a local variable.
        foreach ($rehearsalModule->restControllers() as $rehearsalRestController) {
            add_action('rest_api_init', [$rehearsalRestController, 'register_routes']);
        }

        // StageArt Core/Module Architecture Phase 2 Performance基盤: every
        // Performance Module REST Controller is constructed inside
        // PerformanceModuleBootstrap - registered identically to every
        // other Controller here.
        foreach ($performanceModule->restControllers() as $performanceRestController) {
            add_action('rest_api_init', [$performanceRestController, 'register_routes']);
        }

        // StageArt Core/Module Architecture Phase 3 Ticket/Reservation
        // 基盤: every Ticket Module and Reservation Module REST Controller
        // is constructed inside their own Bootstraps - registered
        // identically to every other Controller here.
        foreach ($ticketModule->restControllers() as $ticketRestController) {
            add_action('rest_api_init', [$ticketRestController, 'register_routes']);
        }

        foreach ($reservationModule->restControllers() as $reservationRestController) {
            add_action('rest_api_init', [$reservationRestController, 'register_routes']);
        }

        // アンケート実装指示書: every Questionnaire Module REST Controller is
        // constructed inside QuestionnaireModuleBootstrap - registered
        // identically to every other Controller here.
        foreach ($questionnaireModule->restControllers() as $questionnaireRestController) {
            add_action('rest_api_init', [$questionnaireRestController, 'register_routes']);
        }

        add_action('rest_api_init', [$notificationRestController, 'register_routes']);
        add_action('rest_api_init', [$dashboardRestController, 'register_routes']);
        add_action('rest_api_init', [$pushPreferenceRestController, 'register_routes']);
        add_action('rest_api_init', [$notificationEmailRestController, 'register_routes']);
        add_action('rest_api_init', [$meRestController, 'register_routes']);
        add_action('rest_api_init', [$joinKeyRestController, 'register_routes']);
        add_action('rest_api_init', [$membershipRestController, 'register_routes']);
        add_action('rest_api_init', [$favoriteRestController, 'register_routes']);

        // StageArt Core/Module Architecture Phase 3 (continued): every
        // Accounting Module REST Controller (Account/Budget/Expense/
        // JournalEntry/ProductionAccounting) is constructed inside
        // AccountingModuleBootstrap - registered identically to every
        // other Controller here.
        foreach ($accountingModule->restControllers() as $accountingRestController) {
            add_action('rest_api_init', [$accountingRestController, 'register_routes']);
        }

        // StageArt Core/Module Architecture Phase 4 (Check-in/精算/
        // 会計連携): every Check-in Module and Settlement Module REST
        // Controller is constructed inside their own Bootstraps -
        // registered identically to every other Controller here.
        foreach ($checkInModule->restControllers() as $checkInRestController) {
            add_action('rest_api_init', [$checkInRestController, 'register_routes']);
        }

        foreach ($settlementModule->restControllers() as $settlementRestController) {
            add_action('rest_api_init', [$settlementRestController, 'register_routes']);
        }

        foreach ($memberPerformanceSummaryModule->restControllers() as $memberPerformanceSummaryRestController) {
            add_action('rest_api_init', [$memberPerformanceSummaryRestController, 'register_routes']);
        }

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

        (new AccountManagementAdminPage(
            $listAllUserAccounts,
            $blockUserAccounts,
            $deleteUserAccounts,
            $requestPasswordReset
        ))->register();

        (new AdminConsoleAccountAdminPage($createAdminConsoleAccount))->register();
    }
}
