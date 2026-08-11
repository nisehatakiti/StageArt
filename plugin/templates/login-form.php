<?php

if (! defined('ABSPATH')) {
    exit;
}

if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    ?>
    <div class="stageart-login">
        <p class="stageart-login__already">
            <?php
            printf(
                /* translators: %s: display name */
                esc_html__('%s としてログイン済みです。', 'stageart'),
                esc_html($current_user->display_name)
            );
            ?>
        </p>
    </div>
    <?php
    return;
}
?>
<div class="stageart-login">
    <form class="stageart-login__form" id="stageart-login-form" novalidate>
        <p class="stageart-login__error" id="stageart-login-error" role="alert" hidden></p>

        <div class="stageart-login__field">
            <label for="stageart-login-email"><?php esc_html_e('メールアドレス', 'stageart'); ?></label>
            <input type="email" id="stageart-login-email" name="email" autocomplete="username" required>
        </div>

        <div class="stageart-login__field">
            <label for="stageart-login-password"><?php esc_html_e('パスワード', 'stageart'); ?></label>
            <input type="password" id="stageart-login-password" name="password" autocomplete="current-password" required>
        </div>

        <button type="submit" class="stageart-login__submit"><?php esc_html_e('ログイン', 'stageart'); ?></button>
    </form>
</div>
