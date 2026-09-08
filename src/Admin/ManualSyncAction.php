<?php
/**
 * Authorized POST endpoint for a manual synchronization run.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Admin;

use PropertySync\Sync\SyncRunner;
use Throwable;

final class ManualSyncAction {

	public const ACTION = 'property_sync_run';

	private SyncRunner $runner;

	public function __construct( SyncRunner $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Register the authenticated admin-post action.
	 */
	public function registerHooks(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Authorize, execute, and redirect using post/redirect/get.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run Property Sync.', 'property-sync' ) );
		}

		check_admin_referer( self::ACTION );

		try {
			$result = $this->runner->run()->toArray();
			$notice = 'already_running' === $result['status'] ? 'already_running' : 'success';
			$url    = add_query_arg(
				array(
					'page'                  => Settings::PAGE_SLUG,
					'property_sync_notice'  => $notice,
					'property_sync_created' => $result['created'],
					'property_sync_updated' => $result['updated'],
					'property_sync_skipped' => $result['skipped'],
					'property_sync_errors'  => $result['errors'],
				),
				admin_url( 'admin.php' )
			);
		} catch ( Throwable $exception ) {
			$url = add_query_arg(
				array(
					'page'                 => Settings::PAGE_SLUG,
					'property_sync_notice' => 'error',
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}
}
