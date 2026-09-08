<?php
/**
 * WP-Cron scheduling for property synchronization.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Cron;

use PropertySync\Admin\Settings;
use PropertySync\Sync\SyncRunner;
use Throwable;

final class SyncScheduler
{
	public const HOOK = 'property_sync_run_scheduled';

	private Settings $settings;

	private SyncRunner $runner;

	public function __construct( ?Settings $settings = null, ?SyncRunner $runner = null )
	{
		$this->settings = $settings ?? new Settings();
		$this->runner   = $runner ?? new SyncRunner();
	}

	/**
	 * Register scheduling and execution hooks.
	 */
	public function registerHooks(): void
	{
		add_action( 'init', array( $this, 'ensureScheduled' ) );
		add_action( self::HOOK, array( $this, 'runScheduled' ) );
		add_action( 'update_option_' . Settings::OPTION_NAME, array( $this, 'rescheduleOnSettingsChange' ), 10, 2 );
	}

	/**
	 * Schedule one recurring event for the configured interval.
	 */
	public function ensureScheduled(): void
	{
		$interval = $this->settings->get()['interval'];
		if ( 'disabled' === $interval ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}

		if ( false !== wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, self::HOOK );
	}

	/**
	 * Rebuild the event only when its configured interval changes.
	 *
	 * @param mixed $oldValue Previous settings option.
	 * @param mixed $value New settings option.
	 */
	public function rescheduleOnSettingsChange( mixed $oldValue, mixed $value ): void
	{
		$oldInterval = is_array( $oldValue ) ? (string) ( $oldValue['interval'] ?? 'disabled' ) : 'disabled';
		$newInterval = is_array( $value ) ? (string) ( $value['interval'] ?? 'disabled' ) : 'disabled';

		if ( $oldInterval === $newInterval ) {
			return;
		}

		wp_clear_scheduled_hook( self::HOOK );
		if ( 'disabled' !== $newInterval ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $newInterval, self::HOOK );
		}
	}

	/**
	 * Run cron through the same use case as manual synchronization.
	 */
	public function runScheduled(): void
	{
		try {
			$this->runner->run( 'cron' );
		} catch ( Throwable $exception ) {
			// SyncRunner records a safe failure event; cron should not emit a fatal error.
		}
	}
}
