<?php

declare(strict_types=1);

namespace StageArt\Presentation\Admin;

use InvalidArgumentException;
use StageArt\Application\Authentication\RequestPasswordResetCommand;
use StageArt\Application\Authentication\RequestPasswordResetUseCase;
use StageArt\Application\UserAccount\AdminAccountResult;
use StageArt\Application\UserAccount\BlockUserAccountsCommand;
use StageArt\Application\UserAccount\BlockUserAccountsUseCase;
use StageArt\Application\UserAccount\DeleteUserAccountsCommand;
use StageArt\Application\UserAccount\DeleteUserAccountsUseCase;
use StageArt\Application\UserAccount\ListAllUserAccountsUseCase;

/**
 * StageArt Admin Console V1 (docs/architecture/StageArtAdminConsole.md's
 * "Person / account overview"): plain admin_menu + admin-post.php page,
 * the same shape OrganizationAdminPage.php already established - no JS
 * build step. Every action here independently checks current_user_can()
 * itself (not just the menu's own capability gate), per this Phase's
 * explicit "UI上でボタンを隠すだけの権限制御にしない" requirement -
 * add_menu_page()'s capability argument alone would already reject an
 * unauthorized direct URL visit, but the admin-post.php handlers are a
 * second, independent entry point that needs its own check too.
 */
final class AccountManagementAdminPage
{
    public const CAPABILITY = 'stageart_manage_accounts';
    public const MENU_SLUG = 'stageart-admin-console';
    private const SLUG = self::MENU_SLUG;
    private const BULK_ACTION = 'stageart_admin_console_bulk_action';
    private const PASSWORD_RESET_ACTION = 'stageart_admin_console_password_reset';

    private ListAllUserAccountsUseCase $listAllUserAccounts;
    private BlockUserAccountsUseCase $blockUserAccounts;
    private DeleteUserAccountsUseCase $deleteUserAccounts;
    private RequestPasswordResetUseCase $requestPasswordReset;

    public function __construct(
        ListAllUserAccountsUseCase $listAllUserAccounts,
        BlockUserAccountsUseCase $blockUserAccounts,
        DeleteUserAccountsUseCase $deleteUserAccounts,
        RequestPasswordResetUseCase $requestPasswordReset
    ) {
        $this->listAllUserAccounts = $listAllUserAccounts;
        $this->blockUserAccounts = $blockUserAccounts;
        $this->deleteUserAccounts = $deleteUserAccounts;
        $this->requestPasswordReset = $requestPasswordReset;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_' . self::BULK_ACTION, [$this, 'handle_bulk_action']);
        add_action('admin_post_' . self::PASSWORD_RESET_ACTION, [$this, 'handle_password_reset']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('StageArt Admin Console', 'stageart'),
            __('StageArt Admin Console', 'stageart'),
            self::CAPABILITY,
            self::SLUG,
            [$this, 'render'],
            'dashicons-admin-users'
        );

        add_submenu_page(
            self::SLUG,
            __('Account Management', 'stageart'),
            __('Account Management', 'stageart'),
            self::CAPABILITY,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access the StageArt Admin Console.', 'stageart'));
        }

        $accounts = $this->listAllUserAccounts->execute();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $accounts = array_values(array_filter(
                $accounts,
                static fn (AdminAccountResult $account): bool =>
                    str_contains(mb_strtolower($account->name), $needle)
                    || str_contains(mb_strtolower($account->email), $needle)
            ));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('アカウント管理', 'stageart') . '</h1>';
        $this->render_notice();
        $this->render_search_form($search);
        $this->render_accounts_table($accounts);
        echo '</div>';
    }

    private function render_search_form(string $search): void
    {
        echo '<form method="get" style="margin:1em 0">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="'
            . esc_attr__('氏名またはメールアドレスで検索', 'stageart') . '">';
        echo ' <button type="submit" class="button">' . esc_html__('検索', 'stageart') . '</button>';
        echo '</form>';
    }

    /**
     * @param AdminAccountResult[] $accounts
     */
    private function render_accounts_table(array $accounts): void
    {
        $statusLabels = [
            'ACTIVE' => __('有効', 'stageart'),
            'SUSPENDED' => __('ブロック', 'stageart'),
            'DISABLED' => __('削除済み', 'stageart'),
        ];

        // No hidden `action`/`_wpnonce` fields at the form level - see
        // render_password_reset_button()'s docblock for why: this form
        // now hosts two different submit targets (bulk action, per-row
        // password reset), each via its own `formaction` query string.
        // admin-post.php dispatches on $_REQUEST['action'], and PHP's
        // $_REQUEST always lets a POST-body value of the same name
        // shadow the query string - a real environment check caught
        // exactly this: a hidden `action` input here was silently
        // overriding every row's own formaction, so every per-row
        // action actually ran as this bulk handler instead.
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="stageart-account-bulk-form">';

        echo '<table class="widefat striped"><thead><tr>';
        echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="stageart-select-all"></td>';
        echo '<th>' . esc_html__('氏名', 'stageart') . '</th>';
        echo '<th>' . esc_html__('メールアドレス', 'stageart') . '</th>';
        echo '<th>' . esc_html__('状態', 'stageart') . '</th>';
        echo '<th>' . esc_html__('パスワードリセット', 'stageart') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($accounts as $account) {
            echo '<tr>';
            echo '<th class="check-column"><input type="checkbox" name="user_account_ids[]" value="'
                . esc_attr($account->userAccountId) . '" class="stageart-account-checkbox"></th>';
            echo '<td>' . esc_html($account->name) . '</td>';
            echo '<td>' . esc_html($account->email) . '</td>';
            echo '<td>' . esc_html($statusLabels[$account->status] ?? esc_html($account->status)) . '</td>';
            echo '<td>' . $this->render_password_reset_button($account) . '</td>';
            echo '</tr>';
        }

        if ($accounts === []) {
            echo '<tr><td colspan="5">' . esc_html__('該当するアカウントがありません。', 'stageart') . '</td></tr>';
        }

        echo '</tbody></table>';

        echo '<div style="margin-top:1em">';
        echo '<select name="bulk_action" id="stageart-bulk-action">';
        echo '<option value="">' . esc_html__('操作を選択', 'stageart') . '</option>';
        echo '<option value="delete">' . esc_html__('削除', 'stageart') . '</option>';
        echo '<option value="block">' . esc_html__('ブロック', 'stageart') . '</option>';
        echo '</select> ';

        $bulkActionUrl = add_query_arg(
            ['action' => self::BULK_ACTION, '_wpnonce' => wp_create_nonce(self::BULK_ACTION)],
            admin_url('admin-post.php')
        );
        echo '<button type="submit" class="button button-primary" id="stageart-bulk-submit" formaction="'
            . esc_url($bulkActionUrl) . '" formmethod="post">' . esc_html__('実行', 'stageart') . '</button>';
        echo '</div>';
        echo '</form>';

        $this->render_bulk_form_script();
    }

    /**
     * Deliberately NOT its own <form> - this renders inside
     * render_accounts_table()'s single outer bulk-action <form>, and
     * nesting a <form> inside another <form> is invalid HTML: a real
     * environment check caught the browser silently mis-associating the
     * bulk "実行" button with the wrong (nested) form as a result,
     * submitting a password reset instead of the intended bulk action.
     * formaction/formmethod (HTML5) let this one button target its own
     * admin-post.php action without a second <form> - the button's own
     * name/value pair (`user_account_id`) is the only field submitted
     * when THIS button (not the bulk "実行" button) is clicked, exactly
     * as a real per-row action needs.
     */
    private function render_password_reset_button(AdminAccountResult $account): string
    {
        if (! $account->hasEmailCredential) {
            return '<span class="description">' . esc_html__('対象外（Googleログインのみ）', 'stageart') . '</span>';
        }

        $actionUrl = add_query_arg(
            [
                'action' => self::PASSWORD_RESET_ACTION,
                '_wpnonce' => wp_create_nonce(self::PASSWORD_RESET_ACTION . '_' . $account->userAccountId),
            ],
            admin_url('admin-post.php')
        );

        return '<button type="submit" class="button" name="user_account_id" value="' . esc_attr($account->userAccountId) . '"'
            . ' formaction="' . esc_url($actionUrl) . '" formmethod="post">'
            . esc_html__('パスワードリセット', 'stageart') . '</button>';
    }

    /**
     * Plain inline JS, matching this admin page's own "no JS build step"
     * shape - only "select all" convenience and a dynamic confirm()
     * message (the bulk action itself already only ever POSTs, and is
     * independently re-checked server-side in handle_bulk_action()
     * regardless of what this script does).
     *
     * Per-row password reset buttons live inside this SAME form (see
     * render_password_reset_button()'s own docblock for why) and are
     * also `type="submit"`, so this handler must check
     * `event.submitter` and skip all bulk-specific validation/confirm()
     * unless the bulk "実行" button itself was what triggered the
     * submit - a real environment check caught this: without the
     * submitter check, clicking "パスワードリセット" with no checkbox
     * selected was wrongly blocked by the bulk action's own "アカウント
     * を選択してください" validation.
     */
    private function render_bulk_form_script(): void
    {
        ?>
        <script>
        (function () {
            var selectAll = document.getElementById('stageart-select-all');
            var form = document.getElementById('stageart-account-bulk-form');
            var bulkSubmit = document.getElementById('stageart-bulk-submit');
            if (!selectAll || !form || !bulkSubmit) { return; }

            selectAll.addEventListener('change', function () {
                var boxes = form.querySelectorAll('.stageart-account-checkbox');
                boxes.forEach(function (box) { box.checked = selectAll.checked; });
            });

            form.addEventListener('submit', function (event) {
                if (event.submitter !== bulkSubmit) {
                    return;
                }

                var checked = form.querySelectorAll('.stageart-account-checkbox:checked').length;
                var action = document.getElementById('stageart-bulk-action').value;

                if (!action) {
                    event.preventDefault();
                    alert('<?php echo esc_js(__('操作を選択してください。', 'stageart')); ?>');
                    return;
                }
                if (checked === 0) {
                    event.preventDefault();
                    alert('<?php echo esc_js(__('アカウントを選択してください。', 'stageart')); ?>');
                    return;
                }

                var label = action === 'delete'
                    ? '<?php echo esc_js(__('削除', 'stageart')); ?>'
                    : '<?php echo esc_js(__('ブロック', 'stageart')); ?>';
                var message = '<?php echo esc_js(__('選択した', 'stageart')); ?>' + checked
                    + '<?php echo esc_js(__('件のアカウントを', 'stageart')); ?>' + label
                    + '<?php echo esc_js(__('します。よろしいですか？', 'stageart')); ?>';

                if (!confirm(message)) {
                    event.preventDefault();
                }
            });
        })();
        </script>
        <?php
    }

    private function render_notice(): void
    {
        if (! isset($_GET['stageart_notice'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['stageart_notice']));
        $messages = [
            'blocked' => __('選択したアカウントをブロックしました。', 'stageart'),
            'deleted' => __('選択したアカウントを削除しました。', 'stageart'),
            'password_reset_sent' => __('パスワードリセットメールを送信しました。', 'stageart'),
            'error' => __('操作を実行できませんでした。', 'stageart'),
        ];

        if (! isset($messages[$notice])) {
            return;
        }

        $class = $notice === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
    }

    public function handle_bulk_action(): void
    {
        check_admin_referer(self::BULK_ACTION);

        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'stageart'));
        }

        $action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';
        $userAccountIds = isset($_POST['user_account_ids']) && is_array($_POST['user_account_ids'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['user_account_ids']))
            : [];

        if ($userAccountIds === [] || ! in_array($action, ['block', 'delete'], true)) {
            $this->redirect_with_notice('error');
        }

        try {
            if ($action === 'block') {
                $this->blockUserAccounts->execute(new BlockUserAccountsCommand($userAccountIds));
                $this->redirect_with_notice('blocked');
            }

            $this->deleteUserAccounts->execute(new DeleteUserAccountsCommand($userAccountIds));
            $this->redirect_with_notice('deleted');
        } catch (InvalidArgumentException $exception) {
            $this->redirect_with_notice('error');
        }
    }

    public function handle_password_reset(): void
    {
        $userAccountId = isset($_POST['user_account_id']) ? sanitize_text_field(wp_unslash($_POST['user_account_id'])) : '';
        check_admin_referer(self::PASSWORD_RESET_ACTION . '_' . $userAccountId);

        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'stageart'));
        }

        // Reuses the existing self-service "send a reset link" flow
        // unchanged (RequestPasswordResetUseCase) - the admin never sees
        // or sets a password directly, only triggers the same email the
        // user could have requested themselves. Requires the account's
        // current email, found by re-listing (kept simple over adding a
        // single-account lookup Use Case for this one call).
        $accounts = $this->listAllUserAccounts->execute();
        $email = null;
        foreach ($accounts as $account) {
            if ($account->userAccountId === $userAccountId && $account->hasEmailCredential) {
                $email = $account->email;
                break;
            }
        }

        if ($email === null) {
            $this->redirect_with_notice('error');
        }

        $this->requestPasswordReset->execute(new RequestPasswordResetCommand($email));
        $this->redirect_with_notice('password_reset_sent');
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
