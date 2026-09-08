<?php
/**
 * Smoke checks for the manual sync endpoint registration and dashboard output.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/manual-sync-dashboard.php
 *
 * @package PropertySync
 */

use PropertySync\Activation\Activator;
use PropertySync\Admin\AdminPage;
use PropertySync\Admin\ManualSyncAction;
use PropertySync\Logging\SyncLogger;
use PropertySync\Sync\SyncResult;

Activator::maybeUpgrade();

$admin = get_user_by( 'login', 'admin' );
if ( false === $admin ) {
	throw new RuntimeException( 'The local administrator account is required for this smoke test.' );
}

wp_set_current_user( $admin->ID );

$logger     = new SyncLogger();
$runId      = 'manual-dashboard-smoke';
$lastResult = get_option( SyncLogger::LAST_RESULT_OPTION, null );
$action     = new ManualSyncAction( new PropertySync\Sync\SyncRunner() );

try {
	$logger->log( $runId, 'CREATED', 'property_created', 'Property created.', 'DASHBOARD-1001' );

	$result = new SyncResult( $runId, '2026-09-08T12:00:00Z' );
	$result->incrementProcessed();
	$result->incrementCreated();
	$result->finish( '2026-09-08T12:00:03Z' );
	$logger->storeLastResult( $result );

	$action->registerHooks();
	if ( false === has_action( 'admin_post_' . ManualSyncAction::ACTION, array( $action, 'handle' ) ) ) {
		throw new RuntimeException( 'The manual sync POST endpoint is not registered.' );
	}

	$page = new AdminPage( $logger );
	ob_start();
	$page->render();
	$html = (string) ob_get_clean();

	foreach ( array( 'admin-post.php', 'property_sync_run', 'Sync now', 'DASHBOARD-1001', 'Property created.', 'Processed' ) as $expectedText ) {
		if ( ! str_contains( $html, $expectedText ) ) {
			throw new RuntimeException( "The dashboard is missing {$expectedText}." );
		}
	}
} finally {
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'property_sync_logs', array( 'run_id' => $runId ), array( '%s' ) );

	if ( null === $lastResult ) {
		delete_option( SyncLogger::LAST_RESULT_OPTION );
	} else {
		update_option( SyncLogger::LAST_RESULT_OPTION, $lastResult, false );
	}
}

echo "Manual synchronization dashboard smoke test passed.\n";
