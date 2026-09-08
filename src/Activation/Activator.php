<?php
/**
 * Plugin activation tasks.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Activation;

use PropertySync\PostType\PropertyPostType;

final class Activator
{
	private const VERSION_OPTION = 'property_sync_version';

	/**
	 * Store the version and create rewrite rules for the content model.
	 */
	public static function activate(): void
	{
		if ( false === get_option( self::VERSION_OPTION, false ) ) {
			add_option( self::VERSION_OPTION, PROPERTY_SYNC_VERSION, '', false );
		} else {
			update_option( self::VERSION_OPTION, PROPERTY_SYNC_VERSION, false );
		}

		( new PropertyPostType() )->register();
		flush_rewrite_rules();
	}
}
