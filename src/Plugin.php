<?php
/**
 * Plugin composition root.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync;

use PropertySync\PostType\PropertyPostType;

final class Plugin
{
	private PropertyPostType $propertyPostType;

	/**
	 * Build the plugin with explicit dependencies.
	 */
	public function __construct( ?PropertyPostType $propertyPostType = null )
	{
		$this->propertyPostType = $propertyPostType ?? new PropertyPostType();
	}

	/**
	 * Register the plugin with WordPress.
	 */
	public function register(): void
	{
		$this->propertyPostType->registerHooks();
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
