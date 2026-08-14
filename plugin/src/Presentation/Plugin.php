<?php

declare(strict_types=1);

namespace StageArt\Presentation;

use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Organization\DeleteOrganizationUseCase;
use StageArt\Application\Organization\GetOrganizationUseCase;
use StageArt\Application\Organization\ListOrganizationsUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\UpdateOrganizationUseCase;
use StageArt\Application\Project\ArchiveProjectUseCase;
use StageArt\Application\Project\CreateProjectUseCase;
use StageArt\Application\Project\GetProjectUseCase;
use StageArt\Application\Project\ListProjectsUseCase;
use StageArt\Application\Project\UpdateProjectUseCase;
use StageArt\Infrastructure\WordPress\Persistence\WordPressMembershipRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressOrganizationRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPersonRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressProjectRepository;
use StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader;
use StageArt\Presentation\Admin\OrganizationAdminPage;
use StageArt\Presentation\Admin\ProjectAdminPage;
use StageArt\Presentation\Rest\OrganizationRestController;
use StageArt\Presentation\Rest\ProjectRestController;

final class Plugin
{
    public function boot(): void
    {
        add_action('init', static function (): void {
            load_plugin_textdomain('stageart', false, dirname(plugin_basename(STAGEART_PLUGIN_FILE)) . '/languages');
        });

        SchemaUpgrader::maybeUpgrade();

        global $wpdb;

        $organizations = new WordPressOrganizationRepository($wpdb);
        $people        = new WordPressPersonRepository($wpdb);
        $memberships   = new WordPressMembershipRepository($wpdb);
        $projects      = new WordPressProjectRepository($wpdb);

        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $createOrganization = new CreateOrganizationUseCase($organizations, $people, $memberships);
        $getOrganization    = new GetOrganizationUseCase($organizations, $authorization);
        $listOrganizations  = new ListOrganizationsUseCase($organizations, $memberships, $authorization);
        $updateOrganization = new UpdateOrganizationUseCase($organizations, $authorization);
        $deleteOrganization = new DeleteOrganizationUseCase($organizations, $authorization);

        $createProject = new CreateProjectUseCase($projects, $authorization);
        $getProject     = new GetProjectUseCase($projects, $authorization);
        $listProjects   = new ListProjectsUseCase($projects, $memberships, $authorization);
        $updateProject  = new UpdateProjectUseCase($projects, $authorization);
        $archiveProject = new ArchiveProjectUseCase($projects, $authorization);

        $organizationRestController = new OrganizationRestController(
            $createOrganization,
            $getOrganization,
            $listOrganizations,
            $updateOrganization,
            $deleteOrganization
        );

        $projectRestController = new ProjectRestController(
            $createProject,
            $getProject,
            $listProjects,
            $updateProject,
            $archiveProject
        );

        add_action('rest_api_init', [$organizationRestController, 'register_routes']);
        add_action('rest_api_init', [$projectRestController, 'register_routes']);

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
