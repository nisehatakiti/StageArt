<?php
/**
 * Plugin Name:       StageArt
 * Description:       StageArt WordPress plugin.
 * Version:            0.1.0
 * Requires at least:  6.0
 * Requires PHP:       8.0
 * Author:             StageArt
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        stageart
 * Domain Path:        /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STAGEART_VERSION', '0.1.0' );
define( 'STAGEART_PLUGIN_FILE', __FILE__ );
define( 'STAGEART_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STAGEART_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'StageArt\\';

	if ( ! str_starts_with( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$path     = STAGEART_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_file( $path ) ) {
		require $path;
	}
} );

register_activation_hook( STAGEART_PLUGIN_FILE, [ StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader::class, 'maybeUpgrade' ] );

add_action( 'plugins_loaded', static function (): void {
	( new StageArt\Presentation\Plugin() )->boot();
} );
