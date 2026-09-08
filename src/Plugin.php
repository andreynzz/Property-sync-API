<?php
/**
 * Plugin composition root.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync;

use PropertySync\Admin\AdminPage;
use PropertySync\Admin\Settings;
use PropertySync\PostType\PropertyPostType;

final class Plugin
{
	private PropertyPostType $propertyPostType;

	private Settings $settings;

	private AdminPage $adminPage;

	/**
	 * Build the plugin with explicit dependencies.
	 */
	public function __construct(
		?PropertyPostType $propertyPostType = null,
		?Settings $settings = null,
		?AdminPage $adminPage = null
	)
	{
		$this->propertyPostType = $propertyPostType ?? new PropertyPostType();
		$this->settings         = $settings ?? new Settings();
		$this->adminPage        = $adminPage ?? new AdminPage();
	}

	/**
	 * Register the plugin with WordPress.
	 */
	public function register(): void
	{
		$this->propertyPostType->registerHooks();
		$this->settings->registerHooks();
		$this->adminPage->registerHooks();
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Signal that the plugin and its dependencies are available.
	 */
	public function boot(): void
	{
		/**
		 * Fires after Property Sync API has loaded.
		 *
		 * @param Plugin $plugin Active plugin instance.
		 */
		do_action( 'property_sync_loaded', $this );
	}
}
