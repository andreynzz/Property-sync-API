<?php
/**
 * Smoke checks for synchronization log storage and secret redaction.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/sync-logging.php
 *
 * @package PropertySync
 */

use PropertySync\Activation\Activator;
use PropertySync\Logging\SyncLogger;
use PropertySync\Sync\SyncResult;

Activator::maybeUpgrade();

global $wpdb;

$tableName      = $wpdb->prefix . 'property_sync_logs';
$lastResult     = get_option( SyncLogger::LAST_RESULT_OPTION, null );
$smokeRunId     = 'logging-smoke-run';
$expiredRunId   = 'logging-expired-run';
$logger         = new SyncLogger();

if ( $tableName !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName ) ) ) {
	throw new RuntimeException( 'The synchronization log table was not created.' );
}

try {
	$logger->log(
		$smokeRunId,
		'INFO',
		'logging_smoke',
		'Log record created for smoke test.',
		'LOG-TEST-1001',
		array(
			'source'        => 'smoke',
			'api_token'     => 'must-not-be-stored',
			'nested'        => array(
				'authorization' => 'must-not-be-stored',
				'page'          => 1,
			),
		)
	);

	$records = $logger->getRecent();
	$record  = null;
	foreach ( $records as $candidate ) {
		if ( $smokeRunId === $candidate['run_id'] ) {
			$record = $candidate;
			break;
		}
	}

	if ( null === $record || 'INFO' !== $record['level'] || false !== strpos( (string) $record['context_json'], 'must-not-be-stored' ) || false === strpos( (string) $record['context_json'], '"page":1' ) ) {
		throw new RuntimeException( 'Log context was not stored or redacted as expected.' );
	}

	$result = new SyncResult( $smokeRunId, '2026-09-08T12:00:00Z' );
	$result->incrementCreated();
	$result->finish( '2026-09-08T12:00:02Z' );
	$logger->storeLastResult( $result );

	$savedResult = get_option( SyncLogger::LAST_RESULT_OPTION );
	if ( $smokeRunId !== $savedResult['run_id'] || 2 !== $savedResult['duration'] ) {
		throw new RuntimeException( 'The last synchronization result was not persisted.' );
	}

	$wpdb->insert(
		$tableName,
		array(
			'run_id'       => $expiredRunId,
			'level'        => 'INFO',
			'event'        => 'expired_smoke',
			'message'      => 'Expired smoke log.',
			'context_json' => null,
			'created_at'   => '2000-01-01 00:00:00',
		)
	);
	$logger->prune();

	if ( null !== $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tableName} WHERE run_id = %s", $expiredRunId ) ) ) {
		throw new RuntimeException( 'Expired logs were not pruned.' );
	}
} finally {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tableName} WHERE run_id IN (%s, %s)", $smokeRunId, $expiredRunId ) );

	if ( null === $lastResult ) {
		delete_option( SyncLogger::LAST_RESULT_OPTION );
	} else {
		update_option( SyncLogger::LAST_RESULT_OPTION, $lastResult, false );
	}
}

echo "Synchronization logging smoke test passed.\n";
