<?php
/**
 * Plugin deactivation tasks.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Activation;

use PropertySync\Cron\SyncScheduler;
final class Deactivator
{
	/**
	 * Remove scheduled work and plugin rewrite rules while preserving user data.
	 */
	public static function deactivate(): void
	{
		wp_clear_scheduled_hook( SyncScheduler::HOOK );
		flush_rewrite_rules();
	}
}
