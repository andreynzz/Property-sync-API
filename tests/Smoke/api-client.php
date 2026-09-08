<?php
/**
 * Smoke checks for the paginated property API client.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/api-client.php
 *
 * @package PropertySync
 */

use PropertySync\Admin\Settings;
use PropertySync\Api\ApiException;
use PropertySync\Api\PropertyApiClient;

$optionName = Settings::OPTION_NAME;
$previous   = get_option( $optionName, null );
$settings   = new Settings();
$settings->register();

update_option(
	$optionName,
	array(
		'api_url'   => 'https://api.example.test/properties',
		'api_token' => 'test-token',
		'interval'  => 'disabled',
	),
	false
);

$requests = array();
$filter   = static function ( $preempt, array $args, string $url ) use ( &$requests ) {
	$requests[] = $args;
	$query      = array();
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	$page = (int) ( $query['page'] ?? 1 );

	if ( str_contains( $url, 'invalid-json' ) ) {
		return array(
			'headers'  => array(),
			'body'     => '{invalid json',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
		);
	}

	$payload = 1 === $page
		? array(
			'data' => array( array( 'external_id' => 'PROP-1' ) ),
			'meta' => array( 'page' => 1, 'per_page' => 50, 'total' => 2, 'total_pages' => 2, 'next_page' => 2 ),
		)
		: array(
			'data' => array( array( 'external_id' => 'PROP-2' ) ),
			'meta' => array( 'page' => 2, 'per_page' => 50, 'total' => 2, 'total_pages' => 2, 'next_page' => null ),
		);

	return array(
		'headers'  => array(),
		'body'     => wp_json_encode( $payload ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
	);
};

add_filter( 'pre_http_request', $filter, 10, 3 );

try {
	$properties = ( new PropertyApiClient( $settings ) )->fetchProperties();
	if ( 2 !== count( $properties ) || 'Bearer test-token' !== $requests[0]['headers']['Authorization'] || 15 !== $requests[0]['timeout'] ) {
		throw new RuntimeException( 'The API client did not send the expected paginated request.' );
	}

	update_option(
		$optionName,
		array(
			'api_url'   => 'https://api.example.test/properties?scenario=invalid-json',
			'api_token' => 'test-token',
			'interval'  => 'disabled',
		),
		false
	);

	try {
		( new PropertyApiClient( $settings ) )->fetchProperties();
		throw new RuntimeException( 'The API client accepted malformed JSON.' );
	} catch ( ApiException $exception ) {
		if ( 'Property API returned invalid JSON.' !== $exception->getMessage() ) {
			throw $exception;
		}
	}
} finally {
	remove_filter( 'pre_http_request', $filter, 10 );

	if ( null === $previous ) {
		delete_option( $optionName );
	} else {
		update_option( $optionName, $previous, false );
	}
}

echo "Property API client smoke test passed.\n";
