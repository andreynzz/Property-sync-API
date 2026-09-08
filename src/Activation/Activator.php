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

	private const LOG_SCHEMA_OPTION = 'property_sync_log_schema_version';

	private const LOG_SCHEMA_VERSION = '1.0.0';

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
		self::maybeUpgrade();
		flush_rewrite_rules();
	}

	/**
	 * Create or upgrade the logging table when the stored schema is outdated.
	 */
	public static function maybeUpgrade(): void
	{
		if ( self::LOG_SCHEMA_VERSION === get_option( self::LOG_SCHEMA_OPTION ) ) {
			return;
		}

		global $wpdb;

		$tableName      = $wpdb->prefix . 'property_sync_logs';
		$charsetCollate = $wpdb->get_charset_collate();
		$sql            = "CREATE TABLE {$tableName} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id char(36) NOT NULL,
			level varchar(20) NOT NULL,
			event varchar(64) NOT NULL,
			external_id varchar(191) NULL,
			message text NOT NULL,
			context_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY level (level),
			KEY external_id (external_id),
			KEY created_at (created_at)
		) {$charsetCollate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( false === get_option( self::LOG_SCHEMA_OPTION, false ) ) {
			add_option( self::LOG_SCHEMA_OPTION, self::LOG_SCHEMA_VERSION, '', false );
			return;
		}

		update_option( self::LOG_SCHEMA_OPTION, self::LOG_SCHEMA_VERSION, false );
	}
}
