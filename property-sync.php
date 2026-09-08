<?php
/**
 * Plugin Name:       Property Sync API
 * Plugin URI:        https://github.com/andreynzz/plugin-wp-sync-api
 * Description:       Synchronizes real-estate listings from an external REST API.
 * Version:           0.1.0-dev
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Andrey
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       property-sync
 *
 * @package PropertySync
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PROPERTY_SYNC_VERSION', '0.1.0-dev' );
define( 'PROPERTY_SYNC_FILE', __FILE__ );
define( 'PROPERTY_SYNC_PATH', plugin_dir_path( __FILE__ ) );

$property_sync_autoloader = PROPERTY_SYNC_PATH . 'vendor/autoload.php';

if ( ! is_readable( $property_sync_autoloader ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Property Sync API requires its Composer dependencies. Run composer install before activating it.', 'property-sync' );
			echo '</p></div>';
		}
	);

	return;
}

require_once $property_sync_autoloader;

register_activation_hook(
	PROPERTY_SYNC_FILE,
	array( PropertySync\Activation\Activator::class, 'activate' )
);

register_deactivation_hook(
	PROPERTY_SYNC_FILE,
	array( PropertySync\Activation\Deactivator::class, 'deactivate' )
);

( new PropertySync\Plugin() )->register();
