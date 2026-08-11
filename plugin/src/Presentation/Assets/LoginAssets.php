<?php

declare(strict_types=1);

namespace StageArt\Presentation\Assets;

final class LoginAssets
{
    public function enqueue(): void
    {
        $post = get_post();

        if (! is_singular() || null === $post || ! has_shortcode($post->post_content, 'stageart_login')) {
            return;
        }

        wp_enqueue_style(
            'stageart-login',
            STAGEART_PLUGIN_URL . 'assets/css/login.css',
            [],
            STAGEART_VERSION
        );

        wp_enqueue_script(
            'stageart-login',
            STAGEART_PLUGIN_URL . 'assets/js/login.js',
            [],
            STAGEART_VERSION,
            true
        );

        wp_localize_script('stageart-login', 'StageArtLogin', [
            'restUrl' => esc_url_raw(rest_url('stageart/v1/login')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }
}
