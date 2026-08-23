<?php

declare(strict_types=1);

namespace StageArt\Presentation\Admin;

use InvalidArgumentException;
use StageArt\Application\Organization\ListOrganizationsForPersonQuery;
use StageArt\Application\Organization\ListOrganizationsUseCase;
use StageArt\Application\Project\ArchiveProjectCommand;
use StageArt\Application\Project\ArchiveProjectUseCase;
use StageArt\Application\Project\CreateProjectCommand;
use StageArt\Application\Project\CreateProjectUseCase;
use StageArt\Application\Project\GetProjectQuery;
use StageArt\Application\Project\GetProjectUseCase;
use StageArt\Application\Project\ListProjectsForPersonQuery;
use StageArt\Application\Project\ListProjectsUseCase;
use StageArt\Application\Project\ProjectAccessDeniedException;
use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Application\Project\ProjectResult;
use StageArt\Application\Project\UpdateProjectCommand;
use StageArt\Application\Project\UpdateProjectUseCase;
use StageArt\Domain\Role\RoleKey;

/**
 * Test-purpose minimal admin UI, same rationale as OrganizationAdminPage:
 * plain admin_menu + admin-post.php, no framework. Project.md treats
 * Project as an Internal Domain end users don't operate directly, so this
 * screen exists to exercise the vertical slice rather than as the
 * eventual UX (which will fold Project creation into a future
 * production-creation flow instead of a standalone screen).
 */
final class ProjectAdminPage
{
    private const SLUG = 'stageart-projects';
    private const PARENT_SLUG = 'stageart-organizations';
    private const CREATE_ACTION = 'stageart_create_project';
    private const UPDATE_ACTION = 'stageart_update_project';
    private const ARCHIVE_ACTION = 'stageart_archive_project';

    private CreateProjectUseCase $createProject;
    private GetProjectUseCase $getProject;
    private ListProjectsUseCase $listProjects;
    private UpdateProjectUseCase $updateProject;
    private ArchiveProjectUseCase $archiveProject;
    private ListOrganizationsUseCase $listOrganizations;

    public function __construct(
        CreateProjectUseCase $createProject,
        GetProjectUseCase $getProject,
        ListProjectsUseCase $listProjects,
        UpdateProjectUseCase $updateProject,
        ArchiveProjectUseCase $archiveProject,
        ListOrganizationsUseCase $listOrganizations
    ) {
        $this->createProject = $createProject;
        $this->getProject = $getProject;
        $this->listProjects = $listProjects;
        $this->updateProject = $updateProject;
        $this->archiveProject = $archiveProject;
        $this->listOrganizations = $listOrganizations;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_' . self::CREATE_ACTION, [$this, 'handle_create']);
        add_action('admin_post_' . self::UPDATE_ACTION, [$this, 'handle_update']);
        add_action('admin_post_' . self::ARCHIVE_ACTION, [$this, 'handle_archive']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Projects', 'stageart'),
            __('Projects', 'stageart'),
            'read',
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! is_user_logged_in()) {
            wp_die(esc_html__('You must be logged in.', 'stageart'));
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';

        if ($action === 'edit' && isset($_GET['id'])) {
            $this->render_edit_form(sanitize_text_field(wp_unslash($_GET['id'])));
            return;
        }

        $this->render_list();
    }

    /**
     * @return array<string, string> OrganizationId => Organization name, limited to Organizations the current Person OWNs.
     */
    private function ownedOrganizations(): array
    {
        $organizations = $this->listOrganizations->execute(
            new ListOrganizationsForPersonQuery(get_current_user_id())
        );

        $owned = [];

        foreach ($organizations as $organization) {
            if ($organization->currentPersonRole === RoleKey::OWNER) {
                $owned[$organization->id] = $organization->name;
            }
        }

        return $owned;
    }

    private function render_list(): void
    {
        $results = $this->listProjects->execute(
            new ListProjectsForPersonQuery(get_current_user_id())
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('StageArt Projects', 'stageart') . '</h1>';

        $this->render_notice();
        $this->render_create_form();

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Name', 'stageart') . '</th>';
        echo '<th>' . esc_html__('Organization ID', 'stageart') . '</th>';
        echo '<th>' . esc_html__('Status', 'stageart') . '</th>';
        echo '<th>' . esc_html__('Your Role', 'stageart') . '</th>';
        echo '<th>' . esc_html__('Actions', 'stageart') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($results as $result) {
            $this->render_row($result);
        }

        if ($results === []) {
            echo '<tr><td colspan="5">' . esc_html__('No Projects yet.', 'stageart') . '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function render_row(ProjectResult $result): void
    {
        $name = $result->name !== null && $result->name !== '' ? $result->name : '(' . __('unnamed', 'stageart') . ')';

        echo '<tr>';
        echo '<td>' . esc_html($name) . '</td>';
        echo '<td><code>' . esc_html($result->organizationId) . '</code></td>';
        echo '<td>' . esc_html($result->status) . '</td>';
        echo '<td>' . esc_html($result->currentPersonRole) . '</td>';
        echo '<td>';

        if ($result->currentPersonRole === RoleKey::OWNER) {
            $editUrl = add_query_arg(
                ['page' => self::SLUG, 'action' => 'edit', 'id' => $result->id],
                admin_url('admin.php')
            );
            echo '<a href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'stageart') . '</a> | ';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php'))
                . '" style="display:inline" onsubmit="return confirm(\''
                . esc_js(__('Archive this Project?', 'stageart')) . '\');">';
            echo '<input type="hidden" name="action" value="' . esc_attr(self::ARCHIVE_ACTION) . '">';
            echo '<input type="hidden" name="project_id" value="' . esc_attr($result->id) . '">';
            wp_nonce_field(self::ARCHIVE_ACTION . '_' . $result->id);
            echo '<button type="submit" class="button-link-delete">' . esc_html__('Archive', 'stageart') . '</button>';
            echo '</form>';
        }

        echo '</td>';
        echo '</tr>';
    }

    private function render_create_form(): void
    {
        $owned = $this->ownedOrganizations();

        echo '<h2>' . esc_html__('Add New Project', 'stageart') . '</h2>';

        if ($owned === []) {
            echo '<p>' . esc_html__('You must own at least one Organization to create a Project.', 'stageart') . '</p><hr>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CREATE_ACTION) . '">';
        wp_nonce_field(self::CREATE_ACTION);
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="stageart-organization">' . esc_html__('Organization', 'stageart')
            . '</label></th><td><select id="stageart-organization" name="organization_id">';

        foreach ($owned as $organizationId => $organizationName) {
            echo '<option value="' . esc_attr($organizationId) . '">' . esc_html($organizationName) . '</option>';
        }

        echo '</select></td></tr>';
        echo '<tr><th><label for="stageart-name">' . esc_html__('Name', 'stageart')
            . '</label></th><td><input type="text" id="stageart-name" name="name" class="regular-text"> <span class="description">'
            . esc_html__('Optional', 'stageart') . '</span></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Create Project', 'stageart'));
        echo '</form><hr>';
    }

    private function render_edit_form(string $projectId): void
    {
        try {
            $result = $this->getProject->execute(
                new GetProjectQuery($projectId, get_current_user_id())
            );
        } catch (ProjectAccessDeniedException $exception) {
            wp_die(esc_html($exception->getMessage()));
            return;
        } catch (ProjectNotFoundException $exception) {
            wp_die(esc_html($exception->getMessage()));
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Edit Project', 'stageart') . '</h1>';
        $this->render_notice();

        if ($result->currentPersonRole !== RoleKey::OWNER) {
            echo '<p>' . esc_html__('Only an Organization Owner can edit this Project.', 'stageart') . '</p>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::UPDATE_ACTION) . '">';
        echo '<input type="hidden" name="project_id" value="' . esc_attr($result->id) . '">';
        wp_nonce_field(self::UPDATE_ACTION . '_' . $result->id);
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>' . esc_html__('Organization ID', 'stageart') . '</th><td><code>'
            . esc_html($result->organizationId) . '</code></td></tr>';
        echo '<tr><th><label for="stageart-name">' . esc_html__('Name', 'stageart')
            . '</label></th><td><input type="text" id="stageart-name" name="name" class="regular-text" value="'
            . esc_attr((string) $result->name) . '"></td></tr>';
        echo '<tr><th><label for="stageart-status">' . esc_html__('Status', 'stageart')
            . '</label></th><td><select id="stageart-status" name="status">';

        foreach (['DRAFT', 'ACTIVE', 'CLOSED', 'ARCHIVED'] as $status) {
            echo '<option value="' . esc_attr($status) . '"' . selected($result->status, $status, false) . '>'
                . esc_html($status) . '</option>';
        }

        echo '</select></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Save Changes', 'stageart'));
        echo '</form>';
        echo '</div>';
    }

    private function render_notice(): void
    {
        if (! isset($_GET['stageart_notice'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['stageart_notice']));
        $messages = [
            'created' => __('Project created.', 'stageart'),
            'updated' => __('Project updated.', 'stageart'),
            'archived' => __('Project archived.', 'stageart'),
            'error' => __('Something went wrong.', 'stageart'),
        ];

        if (! isset($messages[$notice])) {
            return;
        }

        $class = $notice === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
    }

    public function handle_create(): void
    {
        check_admin_referer(self::CREATE_ACTION);

        if (! is_user_logged_in()) {
            wp_die(esc_html__('You must be logged in.', 'stageart'));
        }

        try {
            $this->createProject->execute(new CreateProjectCommand(
                isset($_POST['organization_id']) ? sanitize_text_field(wp_unslash($_POST['organization_id'])) : '',
                get_current_user_id(),
                $this->postStringOrNull('name')
            ));

            $this->redirect_with_notice('created');
        } catch (ProjectAccessDeniedException $exception) {
            wp_die(esc_html($exception->getMessage()));
        } catch (InvalidArgumentException $exception) {
            $this->redirect_with_notice('error');
        }
    }

    public function handle_update(): void
    {
        $projectId = isset($_POST['project_id']) ? sanitize_text_field(wp_unslash($_POST['project_id'])) : '';
        check_admin_referer(self::UPDATE_ACTION . '_' . $projectId);

        try {
            $this->updateProject->execute(new UpdateProjectCommand(
                $projectId,
                get_current_user_id(),
                $this->postStringOrNull('name'),
                isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'DRAFT'
            ));

            $this->redirect_with_notice('updated');
        } catch (ProjectAccessDeniedException $exception) {
            wp_die(esc_html($exception->getMessage()));
        } catch (ProjectNotFoundException $exception) {
            wp_die(esc_html($exception->getMessage()));
        } catch (InvalidArgumentException $exception) {
            $this->redirect_with_notice('error');
        }
    }

    public function handle_archive(): void
    {
        $projectId = isset($_POST['project_id']) ? sanitize_text_field(wp_unslash($_POST['project_id'])) : '';
        check_admin_referer(self::ARCHIVE_ACTION . '_' . $projectId);

        try {
            $this->archiveProject->execute(new ArchiveProjectCommand($projectId, get_current_user_id()));
            $this->redirect_with_notice('archived');
        } catch (ProjectAccessDeniedException $exception) {
            wp_die(esc_html($exception->getMessage()));
        } catch (ProjectNotFoundException $exception) {
            wp_die(esc_html($exception->getMessage()));
        }
    }

    private function postStringOrNull(string $key): ?string
    {
        if (! isset($_POST[$key]) || $_POST[$key] === '') {
            return null;
        }

        return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    private function redirect_with_notice(string $notice): void
    {
        $url = add_query_arg(
            ['page' => self::SLUG, 'stageart_notice' => $notice],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
