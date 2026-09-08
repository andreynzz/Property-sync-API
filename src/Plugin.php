<?php
/**
 * Plugin composition root.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync;

use PropertySync\Admin\AdminPage;
use PropertySync\Admin\ManualSyncAction;
use PropertySync\Admin\Settings;
use PropertySync\Activation\Activator;
use PropertySync\Api\PropertyApiClient;
use PropertySync\Logging\SyncLogger;
use PropertySync\PostType\PropertyPostType;
use PropertySync\Sync\SyncRunner;

final class Plugin
{
	private PropertyPostType $propertyPostType;

	private Settings $settings;

	private AdminPage $adminPage;

	private ManualSyncAction $manualSyncAction;

	/**
	 * Build the plugin with explicit dependencies.
	 */
	public function __construct(
		?PropertyPostType $propertyPostType = null,
		?Settings $settings = null,
		?AdminPage $adminPage = null,
		?ManualSyncAction $manualSyncAction = null,
		?SyncLogger $logger = null,
		?SyncRunner $syncRunner = null
	)
	{
		$this->propertyPostType = $propertyPostType ?? new PropertyPostType();
		$this->settings         = $settings ?? new Settings();
		$logger                 = $logger ?? new SyncLogger();
		$syncRunner             = $syncRunner ?? new SyncRunner( new PropertyApiClient( $this->settings ), null, null, null, $logger );
		$this->adminPage        = $adminPage ?? new AdminPage( $logger );
		$this->manualSyncAction = $manualSyncAction ?? new ManualSyncAction( $syncRunner );
	}

	/**
	 * Register the plugin with WordPress.
	 */
	public function register(): void
	{
		$this->propertyPostType->registerHooks();
		$this->settings->registerHooks();
		$this->adminPage->registerHooks();
		$this->manualSyncAction->registerHooks();
		add_action( 'plugins_loaded', array( Activator::class, 'maybeUpgrade' ) );
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
