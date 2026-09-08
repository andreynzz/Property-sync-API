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
			<p><?php esc_html_e( 'No synchronization has run yet. A run summary will appear here after the sync workflow is added.', 'property-sync' ); ?></p>
		</section>

		<section class="property-sync-admin__panel property-sync-admin__panel--wide">
			<h2><?php esc_html_e( 'Recent activity', 'property-sync' ); ?></h2>
			<p><?php esc_html_e( 'Synchronization logs will appear here once logging is enabled.', 'property-sync' ); ?></p>
		</section>
	</div>
</div>
