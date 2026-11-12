<?php

declare(strict_types=1);

namespace StageArt\Presentation\Admin;

use InvalidArgumentException;
use StageArt\Application\Admin\AdminConsoleAccountCreationException;
use StageArt\Application\Admin\CreateAdminConsoleAccountCommand;
use StageArt\Application\Admin\CreateAdminConsoleAccountUseCase;

/**
 * StageArt Admin Console V1 (§B, "管理者アカウント作成"): a WordPress
 * User + email is required by wp_insert_user() itself even though the
 * instruction's minimum list was username/password/confirmation only -
 * this is the "既存認証仕様上さらに必要な項目" the instruction asked to
 * be reported before implementing, added here as a required field.
 */
final class AdminConsoleAccountAdminPage
{
    private const SLUG = 'stageart-admin-console-accounts';
    private const CREATE_ACTION = 'stageart_admin_console_create_account';

    private CreateAdminConsoleAccountUseCase $createAdminConsoleAccount;

    public function __construct(CreateAdminConsoleAccountUseCase $createAdminConsoleAccount)
    {
        $this->createAdminConsoleAccount = $createAdminConsoleAccount;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_' . self::CREATE_ACTION, [$this, 'handle_create']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            AccountManagementAdminPage::MENU_SLUG,
            __('Admin Accounts', 'stageart'),
            __('管理者アカウント', 'stageart'),
            AccountManagementAdminPage::CAPABILITY,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can(AccountManagementAdminPage::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access the StageArt Admin Console.', 'stageart'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('管理者アカウント作成', 'stageart') . '</h1>';
        $this->render_notice();

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CREATE_ACTION) . '">';
        wp_nonce_field(self::CREATE_ACTION);
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="stageart-admin-username">' . esc_html__('管理者ユーザー名', 'stageart')
            . '</label></th><td><input type="text" id="stageart-admin-username" name="username" class="regular-text" required autocomplete="off"></td></tr>';
        echo '<tr><th><label for="stageart-admin-email">' . esc_html__('メールアドレス', 'stageart')
            . '</label></th><td><input type="email" id="stageart-admin-email" name="email" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="stageart-admin-password">' . esc_html__('パスワード', 'stageart')
            . '</label></th><td><input type="password" id="stageart-admin-password" name="password" class="regular-text" required minlength="8" autocomplete="new-password"></td></tr>';
        echo '<tr><th><label for="stageart-admin-password-confirm">' . esc_html__('パスワード確認', 'stageart')
            . '</label></th><td><input type="password" id="stageart-admin-password-confirm" name="password_confirmation" class="regular-text" required minlength="8" autocomplete="new-password"></td></tr>';
        echo '</tbody></table>';
        submit_button(__('管理者アカウントを作成', 'stageart'));
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
            'created' => __('管理者アカウントを作成しました。', 'stageart'),
            'password_mismatch' => __('パスワードとパスワード確認が一致しません。', 'stageart'),
            'error' => __('管理者アカウントを作成できませんでした。', 'stageart'),
        ];

        if (! isset($messages[$notice])) {
            return;
        }

        $class = in_array($notice, ['password_mismatch', 'error'], true) ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
    }

    public function handle_create(): void
    {
        check_admin_referer(self::CREATE_ACTION);

        if (! current_user_can(AccountManagementAdminPage::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'stageart'));
        }

        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $passwordConfirmation = isset($_POST['password_confirmation']) ? (string) wp_unslash($_POST['password_confirmation']) : '';

        if ($password !== $passwordConfirmation) {
            $this->redirect_with_notice('password_mismatch');
        }

        try {
            $this->createAdminConsoleAccount->execute(new CreateAdminConsoleAccountCommand(
                isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '',
                isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
                $password
            ));

            $this->redirect_with_notice('created');
        } catch (InvalidArgumentException $exception) {
            $this->redirect_with_notice('error');
        } catch (AdminConsoleAccountCreationException $exception) {
            $this->redirect_with_notice('error');
        }
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
