<?php

declare(strict_types=1);

namespace StageArt\Presentation\Shortcode;

final class LoginShortcode
{
    public function register(): void
    {
        add_shortcode('stageart_login', [$this, 'render']);
    }

    public function render(): string
    {
        ob_start();
        include STAGEART_PLUGIN_DIR . 'templates/login-form.php';

        return (string) ob_get_clean();
    }
}
