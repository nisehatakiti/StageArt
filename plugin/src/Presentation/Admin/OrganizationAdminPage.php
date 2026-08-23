<?php

declare(strict_types=1);

namespace StageArt\Presentation\Admin;

use InvalidArgumentException;
use StageArt\Application\Organization\CreateOrganizationCommand;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Organization\DeleteOrganizationCommand;
use StageArt\Application\Organization\DeleteOrganizationUseCase;
use StageArt\Application\Organization\GetOrganizationQuery;
use StageArt\Application\Organization\GetOrganizationUseCase;
use StageArt\Application\Organization\ListOrganizationsForPersonQuery;
use StageArt\Application\Organization\ListOrganizationsUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\Organization\OrganizationResult;
use StageArt\Application\Organization\UpdateOrganizationCommand;
use StageArt\Application\Organization\UpdateOrganizationUseCase;
use StageArt\Domain\Role\RoleKey;

/**
 * Plain admin_menu + admin-post.php page: no JS build step, matching
 * "Simple UI" and the fact that FrontendArchitecture.md does not mandate
 * a framework for wp-admin screens. Capability checks here are
 * intentionally coarse (is_user_logged_in only); the real Organization
 * Scope decision is made by the Use Cases, same as OrganizationRestController.
 */
final class OrganizationAdminPage
{
    private const SLUG = 'stageart-organizations';
    private const CREATE_ACTION = 'stageart_create_organization';
    private const UPDATE_ACTION = 'stageart_update_organization';
    private const DELETE_ACTION = 'stageart_delete_organization';

    private CreateOrganizationUseCase $createOrganization;
    private GetOrganizationUseCase $getOrganization;
    private ListOrganizationsUseCase $listOrganizations;
    private UpdateOrganizationUseCase $updateOrganization;
    private DeleteOrganizationUseCase $deleteOrganization;

    public function __construct(
        CreateOrganizationUseCase $createOrganization,
        GetOrganizationUseCase $getOrganization,
        ListOrganizationsUseCase $listOrganizations,
        UpdateOrganizationUseCase $updateOrganization,
        DeleteOrganizationUseCase $deleteOrganization
    ) {
        $this->createOrganization = $createOrganization;
        $this->getOrganization = $getOrganization;
        $this->listOrganizations = $listOrganizations;
        $this->updateOrganization = $updateOrganization;
        $this->deleteOrganization = $deleteOrganization;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_' . self::CREATE_ACTION, [$this, 'handle_create']);
        add_action('admin_post_' . self::UPDATE_ACTION, [$this, 'handle_update']);
        add_action('admin_post_' . self::DELETE_ACTION, [$this, 'handle_delete']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('StageArt', 'stageart'),
            __('StageArt', 'stageart'),
            'read',
            self::SLUG,
            [$this, 'render'],
            'dashicons-groups'
        );

        add_submenu_page(
            self::SLUG,
            __('Organizations', 'stageart'),
            __('Organizations', 'stageart'),
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

    private function render_list(): void
    {
        $results = $this->listOrganizations->execute(
            new ListOrganizationsForPersonQuery(get_current_user_id())
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('StageArt Organizations', 'stageart') . '</h1>';

        $this->render_notice();
        $this->render_create_form();

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Name', 'stageart') . '</th>';
        echo '<th>' . esc_html__('Type', 'stageart') . '</th>';
        echo '<th>' . esc_html__('Status', 'stageart') . '</th>';
        echo '<th>' . esc_html__('Your Role', 'stageart') . '</th>';
        echo '<th>' . esc_html__('Actions', 'stageart') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($results as $result) {
            $this->render_row($result);
        }

        if ($results === []) {
            echo '<tr><td colspan="5">' . esc_html__('No Organizations yet.', 'stageart') . '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function render_row(OrganizationResult $result): void
    {
        echo '<tr>';
        echo '<td>' . esc_html($result->name) . '</td>';
        echo '<td>' . esc_html((string) $result->type) . '</td>';
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
                . esc_js(__('Delete this Organization?', 'stageart')) . '\');">';
            echo '<input type="hidden" name="action" value="' . esc_attr(self::DELETE_ACTION) . '">';
            echo '<input type="hidden" name="organization_id" value="' . esc_attr($result->id) . '">';
            wp_nonce_field(self::DELETE_ACTION . '_' . $result->id);
            echo '<button type="submit" class="button-link-delete">' . esc_html__('Delete', 'stageart') . '</button>';
            echo '</form>';
        }

        echo '</td>';
        echo '</tr>';
    }

    private function render_create_form(): void
    {
        echo '<h2>' . esc_html__('Add New Organization', 'stageart') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CREATE_ACTION) . '">';
        wp_nonce_field(self::CREATE_ACTION);
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="stageart-name">' . esc_html__('Name', 'stageart')
            . '</label></th><td><input type="text" id="stageart-name" name="name" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="stageart-type">' . esc_html__('Type', 'stageart')
            . '</label></th><td><input type="text" id="stageart-type" name="type" class="regular-text"></td></tr>';
        echo '<tr><th><label for="stageart-description">' . esc_html__('Description', 'stageart')
            . '</label></th><td><textarea id="stageart-description" name="description" class="large-text" rows="3"></textarea></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Create Organization', 'stageart'));
        echo '</form><hr>';
    }

    private function render_edit_form(string $organizationId): void
    {
        try {
            $result = $this->getOrganization->execute(
                new GetOrganizationQuery($organizationId, get_current_user_id())
            );
        } catch (OrganizationAccessDeniedException $exception) {
            wp_die(esc_html($exception->getMessage()));
            return;
        } catch (OrganizationNotFoundException $exception) {
            wp_die(esc_html($exception->getMessage()));
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Edit Organization', 'stageart') . '</h1>';
        $this->render_notice();

        if ($result->currentPersonRole !== RoleKey::OWNER) {
            echo '<p>' . esc_html__('Only an Organization Owner can edit this Organization.', 'stageart') . '</p>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::UPDATE_ACTION) . '">';
        echo '<input type="hidden" name="organization_id" value="' . esc_attr($result->id) . '">';
        wp_nonce_field(self::UPDATE_ACTION . '_' . $result->id);
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="stageart-name">' . esc_html__('Name', 'stageart')
            . '</label></th><td><input type="text" id="stageart-name" name="name" class="regular-text" value="'
            . esc_attr($result->name) . '" required></td></tr>';
        echo '<tr><th><label for="stageart-type">' . esc_html__('Type', 'stageart')
            . '</label></th><td><input type="text" id="stageart-type" name="type" class="regular-text" value="'
            . esc_attr((string) $result->type) . '"></td></tr>';
        echo '<tr><th><label for="stageart-description">' . esc_html__('Description', 'stageart')
            . '</label></th><td><textarea id="stageart-description" name="description" class="large-text" rows="3">'
            . esc_textarea((string) $result->description) . '</textarea></td></tr>';
        echo '<tr><th><label for="stageart-status">' . esc_html__('Status', 'stageart')
            . '</label></th><td><select id="stageart-status" name="status">';

        foreach (['ACTIVE', 'INACTIVE', 'ARCHIVED'] as $status) {
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
            'created' => __('Organization created.', 'stageart'),
            'updated' => __('Organization updated.', 'stageart'),
            'deleted' => __('Organization deleted.', 'stageart'),
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
            $this->createOrganization->execute(new CreateOrganizationCommand(
                get_current_user_id(),
                isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
                $this->postStringOrNull('type'),
                $this->postStringOrNull('description')
            ));

            $this->redirect_with_notice('created');
        } catch (InvalidArgumentException $exception) {
            $this->redirect_with_notice('error');
        }
    }

    public function handle_update(): void
    {
        $organizationId = isset($_POST['organization_id']) ? sanitize_text_field(wp_unslash($_POST['organization_id'])) : '';
        check_admin_referer(self::UPDATE_ACTION . '_' . $organizationId);

        try {
            $this->updateOrganization->execute(new UpdateOrganizationCommand(
                $organizationId,
                get_current_user_id(),
                isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
                $this->postStringOrNull('type'),
                $this->postStringOrNull('description'),
                isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'ACTIVE'
            ));

            $this->redirect_with_notice('updated');
        } catch (OrganizationAccessDeniedException $exception) {
            wp_die(esc_html($exception->getMessage()));
        } catch (OrganizationNotFoundException $exception) {
            wp_die(esc_html($exception->getMessage()));
        } catch (InvalidArgumentException $exception) {
            $this->redirect_with_notice('error');
        }
    }

    public function handle_delete(): void
    {
        $organizationId = isset($_POST['organization_id']) ? sanitize_text_field(wp_unslash($_POST['organization_id'])) : '';
        check_admin_referer(self::DELETE_ACTION . '_' . $organizationId);

        try {
            $this->deleteOrganization->execute(new DeleteOrganizationCommand($organizationId, get_current_user_id()));
            $this->redirect_with_notice('deleted');
        } catch (OrganizationAccessDeniedException $exception) {
            wp_die(esc_html($exception->getMessage()));
        } catch (OrganizationNotFoundException $exception) {
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
