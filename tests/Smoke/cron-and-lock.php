<?php
/**
 * Smoke checks for WP-Cron scheduling and synchronization locks.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/cron-and-lock.php
 *
 * @package PropertySync
 */

use PropertySync\Admin\Settings;
use PropertySync\Activation\Deactivator;
use PropertySync\Cron\SyncScheduler;
use PropertySync\Sync\SyncLock;
use PropertySync\Sync\SyncRunner;

$settingsOption = Settings::OPTION_NAME;
$previousSettings = get_option( $settingsOption, null );
$previousLock     = get_option( SyncLock::OPTION_NAME, null );
$lock             = new SyncLock();
$settings         = new Settings();
$scheduler        = new SyncScheduler( $settings );

try {
	delete_option( SyncLock::OPTION_NAME );

	$token = $lock->acquire( 'manual' );
	if ( null === $token || null !== $lock->acquire( 'cron' ) ) {
		throw new RuntimeException( 'The synchronization lock was not acquired atomically.' );
	}

	$lock->release( 'not-the-owner' );
	if ( null === get_option( SyncLock::OPTION_NAME, null ) ) {
		throw new RuntimeException( 'A non-owner released the synchronization lock.' );
	}

	$lock->release( $token );
	if ( null !== get_option( SyncLock::OPTION_NAME, null ) ) {
		throw new RuntimeException( 'The synchronization lock owner could not release it.' );
	}

	add_option(
		SyncLock::OPTION_NAME,
		array(
			'token'      => 'expired-token',
			'started_at' => time() - 901,
			'source'     => 'manual',
		),
		'',
		false
	);
	$recoveredToken = $lock->acquire( 'cron' );
	if ( null === $recoveredToken || $recoveredToken === 'expired-token' ) {
		throw new RuntimeException( 'An expired synchronization lock was not recovered.' );
	}
	$lock->release( $recoveredToken );

	$token = $lock->acquire( 'manual' );
	$alreadyRunning = ( new SyncRunner() )->run()->toArray();
	if ( 'already_running' !== $alreadyRunning['status'] ) {
		throw new RuntimeException( 'A second synchronization run was not rejected.' );
	}
	$lock->release( $token );

	update_option(
		$settingsOption,
		array(
			'api_url'   => '',
			'api_token' => '',
			'interval'  => 'hourly',
		),
		false
	);
	wp_clear_scheduled_hook( SyncScheduler::HOOK );
	$scheduler->ensureScheduled();
	$scheduler->ensureScheduled();
	$timestamp = wp_next_scheduled( SyncScheduler::HOOK );
	if ( false === $timestamp || 'hourly' !== wp_get_schedule( SyncScheduler::HOOK ) ) {
		throw new RuntimeException( 'The hourly synchronization event was not scheduled.' );
	}

	Deactivator::deactivate();
	if ( false !== wp_next_scheduled( SyncScheduler::HOOK ) ) {
		throw new RuntimeException( 'Deactivation did not clear the synchronization event.' );
	}
	$scheduler->ensureScheduled();

	$scheduler->rescheduleOnSettingsChange(
		array( 'interval' => 'hourly' ),
		array( 'interval' => 'disabled' )
	);
	if ( false !== wp_next_scheduled( SyncScheduler::HOOK ) ) {
		throw new RuntimeException( 'The disabled synchronization interval was not cleared.' );
	}

	$failed = false;
	try {
		( new SyncRunner() )->run();
	} catch ( Throwable $exception ) {
		$failed = true;
	}
	if ( ! $failed ) {
		throw new RuntimeException( 'An unconfigured API run unexpectedly succeeded.' );
	}

	if ( null !== get_option( SyncLock::OPTION_NAME, null ) ) {
		throw new RuntimeException( 'The synchronization lock was not released after a failed run.' );
	}
} finally {
	wp_clear_scheduled_hook( SyncScheduler::HOOK );
	delete_option( SyncLock::OPTION_NAME );

	if ( null !== $previousLock ) {
		add_option( SyncLock::OPTION_NAME, $previousLock, '', false );
	}

	if ( null === $previousSettings ) {
		delete_option( $settingsOption );
	} else {
		update_option( $settingsOption, $previousSettings, false );
	}
}

echo "Cron and synchronization lock smoke test passed.\n";
