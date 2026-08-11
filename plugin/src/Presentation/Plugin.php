<?php

declare(strict_types=1);

namespace StageArt\Presentation;

use StageArt\Application\Auth\LoginUseCase;
use StageArt\Infrastructure\WordPress\WordPressUserAuthenticator;
use StageArt\Presentation\Assets\LoginAssets;
use StageArt\Presentation\Rest\LoginRestController;
use StageArt\Presentation\Shortcode\LoginShortcode;

final class Plugin
{
    public function boot(): void
    {
        $loginUseCase = new LoginUseCase(new WordPressUserAuthenticator());

        $restController = new LoginRestController($loginUseCase);
        $shortcode      = new LoginShortcode();
        $assets         = new LoginAssets();

        add_action('rest_api_init', [$restController, 'register_routes']);
        add_action('init', [$shortcode, 'register']);
        add_action('wp_enqueue_scripts', [$assets, 'enqueue']);

        add_action('init', static function (): void {
            load_plugin_textdomain('stageart', false, dirname(plugin_basename(STAGEART_PLUGIN_FILE)) . '/languages');
        });
    }
}
