<?php
/**
 * Integration smoke check for the Docker mock API.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/api-client-mock.php
 *
 * @package PropertySync
 */

use PropertySync\Admin\Settings;
use PropertySync\Api\PropertyApiClient;

if ( defined( 'PROPERTY_SYNC_API_TOKEN' ) ) {
	throw new RuntimeException( 'The mock API integration test requires no environment token override.' );
}

$optionName = Settings::OPTION_NAME;
$previous   = get_option( $optionName, null );
$settings   = new Settings();

try {
	update_option(
		$optionName,
		array(
			'api_url'   => 'http://mock-api:3000/properties',
			'api_token' => 'demo-token',
			'interval'  => 'disabled',
		),
		false
	);

	$properties = ( new PropertyApiClient( $settings ) )->fetchProperties();
	if ( 3 !== count( $properties ) ) {
		throw new RuntimeException( 'The mock API returned an unexpected property count.' );
	}
} finally {
	if ( null === $previous ) {
		delete_option( $optionName );
	} else {
		update_option( $optionName, $previous, false );
	}
}

echo "Property API mock integration check passed.\n";
