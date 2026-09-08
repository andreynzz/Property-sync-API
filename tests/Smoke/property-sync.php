<?php
/**
 * Smoke checks for property persistence and synchronization.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/property-sync.php
 *
 * @package PropertySync
 */

use PropertySync\Admin\Settings;
use PropertySync\Api\PropertyApiClient;
use PropertySync\PostType\PropertyPostType;
use PropertySync\Sync\PropertyRepository;
use PropertySync\Sync\SyncRunner;

$optionName = Settings::OPTION_NAME;
$previous   = get_option( $optionName, null );
$settings   = new Settings();
$repository = new PropertyRepository();
$payload    = array(
	array(
		'external_id'  => 'SYNC-TEST-1001',
		'title'        => 'Initial synchronized property',
		'description'  => 'A property used only by the synchronization smoke test.',
		'price'        => '100000.00',
		'property_type' => 'apartment',
		'city'         => 'Sao Paulo',
		'neighborhood' => 'Pinheiros',
		'bedrooms'     => 2,
		'bathrooms'    => 1,
		'area'         => '50.00',
		'status'       => 'available',
		'image_url'    => 'https://example.com/sync-test.jpg',
		'updated_at'   => '2026-09-08T12:00:00Z',
	),
);
$filter = static function ( $preempt, array $args, string $url ) use ( &$payload ) {
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode(
			array(
				'data' => $payload,
				'meta' => array( 'page' => 1, 'per_page' => 50, 'total' => count( $payload ), 'total_pages' => 1, 'next_page' => null ),
			)
		),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
	);
};

add_filter( 'pre_http_request', $filter, 10, 3 );

try {
	update_option(
		$optionName,
		array(
			'api_url'   => 'https://api.example.test/properties',
			'api_token' => 'test-token',
			'interval'  => 'disabled',
		),
		false
	);

	$runner = new SyncRunner( new PropertyApiClient( $settings ) );
	$first  = $runner->run()->toArray();
	if ( 1 !== $first['processed'] || 1 !== $first['created'] || 0 !== $first['errors'] || null === $first['finished_at'] || null === $first['duration'] ) {
		throw new RuntimeException( 'The first synchronization did not create the property.' );
	}

	$postId = $repository->findIdByExternalId( 'SYNC-TEST-1001' );
	if ( null === $postId || '100000.00' !== get_post_meta( $postId, '_property_price', true ) || ! has_term( 'apartment', PropertyPostType::TYPE_TAXONOMY, $postId ) ) {
		throw new RuntimeException( 'Property metadata or taxonomy terms were not persisted.' );
	}

	$second = $runner->run()->toArray();
	if ( 1 !== $second['skipped'] || 0 !== $second['updated'] ) {
		throw new RuntimeException( 'An unchanged property was not skipped.' );
	}

	$payload[0]['title'] = 'Updated synchronized property';
	$third                = $runner->run()->toArray();
	if ( 1 !== $third['updated'] || 'Updated synchronized property' !== get_the_title( $postId ) ) {
		throw new RuntimeException( 'A changed property was not updated.' );
	}

	unset( $payload[0]['title'] );
	$invalid = $runner->run()->toArray();
	if ( 1 !== $invalid['errors'] || 0 !== $invalid['created'] || 0 !== $invalid['updated'] ) {
		throw new RuntimeException( 'An invalid property did not remain isolated.' );
	}

	$duplicateId = wp_insert_post(
		array(
			'post_type'   => PropertyPostType::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Duplicate external ID',
		),
		true
	);
	if ( is_wp_error( $duplicateId ) ) {
		throw new RuntimeException( 'Unable to set up duplicate integrity test.' );
	}
	update_post_meta( $duplicateId, '_property_external_id', 'SYNC-TEST-1001' );

	try {
		$repository->findIdByExternalId( 'SYNC-TEST-1001' );
		throw new RuntimeException( 'Duplicate external IDs were accepted.' );
	} catch ( RuntimeException $exception ) {
		if ( 'Multiple properties share the same external ID.' !== $exception->getMessage() ) {
			throw $exception;
		}
	}
} finally {
	remove_filter( 'pre_http_request', $filter, 10 );

	$postIds = get_posts(
		array(
			'post_type'      => PropertyPostType::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_property_external_id',
					'value' => 'SYNC-TEST-1001',
				),
			),
		)
	);
	foreach ( $postIds as $postId ) {
		wp_delete_post( $postId, true );
	}

	if ( null === $previous ) {
		delete_option( $optionName );
	} else {
		update_option( $optionName, $previous, false );
	}
}

echo "Property synchronization smoke test passed.\n";
