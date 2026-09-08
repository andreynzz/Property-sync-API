<?php
/**
 * Property Sync admin page template.
 *
 * @package PropertySync
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap property-sync-admin">
	<h1><?php esc_html_e( 'Property Sync', 'property-sync' ); ?></h1>
	<p class="property-sync-admin__intro"><?php esc_html_e( 'Configure the external property feed and review synchronization activity.', 'property-sync' ); ?></p>

	<?php settings_errors( PropertySync\Admin\Settings::OPTION_NAME ); ?>
	<?php if ( 'success' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Synchronization completed. Review the summary below.', 'property-sync' ); ?></p></div>
	<?php elseif ( 'error' === $notice ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Synchronization could not complete. Review recent activity for details.', 'property-sync' ); ?></p></div>
	<?php elseif ( 'already_running' === $notice ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'A synchronization is already running. Try again shortly.', 'property-sync' ); ?></p></div>
	<?php endif; ?>

	<div class="property-sync-admin__grid">
		<section class="property-sync-admin__panel">
			<h2><?php esc_html_e( 'API settings', 'property-sync' ); ?></h2>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'property_sync_settings_group' );
				do_settings_sections( PropertySync\Admin\Settings::PAGE_SLUG );
				submit_button( __( 'Save settings', 'property-sync' ) );
				?>
			</form>
		</section>

		<section class="property-sync-admin__panel">
			<h2><?php esc_html_e( 'Last synchronization', 'property-sync' ); ?></h2>
			<?php if ( is_array( $lastResult ) && isset( $lastResult['finished_at'] ) ) : ?>
				<dl class="property-sync-admin__summary">
					<div><dt><?php esc_html_e( 'Completed', 'property-sync' ); ?></dt><dd><?php echo esc_html( (string) $lastResult['finished_at'] ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Processed', 'property-sync' ); ?></dt><dd><?php echo esc_html( (string) ( $lastResult['processed'] ?? 0 ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Created', 'property-sync' ); ?></dt><dd><?php echo esc_html( (string) ( $lastResult['created'] ?? 0 ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Updated', 'property-sync' ); ?></dt><dd><?php echo esc_html( (string) ( $lastResult['updated'] ?? 0 ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Skipped', 'property-sync' ); ?></dt><dd><?php echo esc_html( (string) ( $lastResult['skipped'] ?? 0 ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Errors', 'property-sync' ); ?></dt><dd><?php echo esc_html( (string) ( $lastResult['errors'] ?? 0 ) ); ?></dd></div>
				</dl>
			<?php else : ?>
				<p><?php esc_html_e( 'No synchronization has run yet.', 'property-sync' ); ?></p>
			<?php endif; ?>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="property_sync_run" />
				<?php wp_nonce_field( PropertySync\Admin\ManualSyncAction::ACTION ); ?>
				<?php submit_button( __( 'Sync now', 'property-sync' ), 'primary', 'submit', false ); ?>
			</form>
		</section>

		<section class="property-sync-admin__panel property-sync-admin__panel--wide">
			<h2><?php esc_html_e( 'Recent activity', 'property-sync' ); ?></h2>
			<?php if ( array() === $recentLogs ) : ?>
				<p><?php esc_html_e( 'No synchronization events have been recorded yet.', 'property-sync' ); ?></p>
			<?php else : ?>
				<div class="property-sync-admin__table-wrap">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Date', 'property-sync' ); ?></th><th><?php esc_html_e( 'Level', 'property-sync' ); ?></th><th><?php esc_html_e( 'Event', 'property-sync' ); ?></th><th><?php esc_html_e( 'Property', 'property-sync' ); ?></th><th><?php esc_html_e( 'Message', 'property-sync' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $recentLogs as $log ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $log['created_at'] ); ?></td>
									<td><span class="property-sync-admin__level property-sync-admin__level--<?php echo esc_attr( strtolower( (string) $log['level'] ) ); ?>"><?php echo esc_html( (string) $log['level'] ); ?></span></td>
									<td><?php echo esc_html( (string) $log['event'] ); ?></td>
									<td><?php echo esc_html( (string) ( $log['external_id'] ?? '—' ) ); ?></td>
									<td><?php echo esc_html( (string) $log['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
	</div>
</div>
