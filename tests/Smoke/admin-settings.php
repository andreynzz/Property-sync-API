<?php
/**
 * Smoke checks for plugin settings.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/admin-settings.php
 *
 * @package PropertySync
 */

use PropertySync\Admin\Settings;

$optionName = Settings::OPTION_NAME;
$previous   = get_option( $optionName, null );
$settings   = new Settings();

$settings->register();

try {
	$valid = $settings->sanitize(
		array(
			'api_url'   => 'http://mock-api:3000/properties',
			'api_token' => 'demo-token',
			'interval'  => 'hourly',
		)
	);
	update_option( $optionName, $valid, false );

	$saved = $settings->get();
	if ( 'http://mock-api:3000/properties' !== $saved['api_url'] || 'hourly' !== $saved['interval'] || 'demo-token' !== $saved['api_token'] ) {
		throw new RuntimeException( 'Valid settings were not persisted.' );
	}

	$preserved = $settings->sanitize(
		array(
			'api_url'   => $saved['api_url'],
			'api_token' => '',
			'interval'  => 'daily',
		)
	);
	if ( 'demo-token' !== $preserved['api_token'] || 'daily' !== $preserved['interval'] ) {
		throw new RuntimeException( 'An empty token field did not preserve the saved token.' );
	}

	$invalid = $settings->sanitize(
		array(
			'api_url'  => 'http://api.example.com/properties',
			'interval' => 'weekly',
		)
	);
	if ( $saved['api_url'] !== $invalid['api_url'] || $saved['interval'] !== $invalid['interval'] ) {
		throw new RuntimeException( 'Invalid settings were accepted.' );
	}

	if ( ! defined( 'PROPERTY_SYNC_API_TOKEN' ) ) {
		define( 'PROPERTY_SYNC_API_TOKEN', 'environment-token' );
	}

	if ( ! $settings->isTokenOverridden() || (string) constant( 'PROPERTY_SYNC_API_TOKEN' ) !== $settings->getApiToken() ) {
		throw new RuntimeException( 'The environment token does not override the saved token.' );
	}
} finally {
	if ( null === $previous ) {
		delete_option( $optionName );
	} else {
		update_option( $optionName, $previous, false );
	}
}

echo "Property Sync settings smoke test passed.\n";
