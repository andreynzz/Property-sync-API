<?php
/**
 * Plugin composition root.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync;

final class Plugin
{
    /**
     * Register the plugin with WordPress.
     */
    public function register(): void
    {
        add_action('plugins_loaded', array($this, 'boot'));
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
        do_action('property_sync_loaded', $this);
    }
}