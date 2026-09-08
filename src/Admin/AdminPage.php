<?php
/**
 * Admin dashboard page.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Admin;

final class AdminPage
{
	/**
	 * Register admin hooks.
	 */
	public function registerHooks(): void
	{
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * Add the top-level dashboard page.
	 */
	public function registerMenu(): void
	{
		add_menu_page(
			__( 'Property Sync', 'property-sync' ),
			__( 'Property Sync', 'property-sync' ),
			'manage_options',
			Settings::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-update',
			21
		);
	}

	/**
	 * Load styles only on this plugin's screen.
	 *
	 * @param string $hookSuffix Current admin screen hook.
	 */
	public function enqueueAssets( string $hookSuffix ): void
	{
		if ( 'toplevel_page_' . Settings::PAGE_SLUG !== $hookSuffix ) {
			return;
		}

		wp_enqueue_style( 'property-sync-admin', plugins_url( 'assets/admin.css', PROPERTY_SYNC_FILE ), array(), PROPERTY_SYNC_VERSION );
	}

	/**
	 * Render the dashboard for authorized administrators.
	 */
	public function render(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Property Sync settings.', 'property-sync' ) );
		}

		$template = PROPERTY_SYNC_PATH . 'templates/admin-page.php';

		if ( is_readable( $template ) ) {
			require $template;
		}
	}
}
