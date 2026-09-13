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
use PropertySync\Logging\SyncLogger;
use PropertySync\PostType\PropertyPostType;
use PropertySync\Sync\PropertyRepository;
use PropertySync\Sync\SyncLock;
use PropertySync\Sync\SyncRunner;

$optionName = Settings::OPTION_NAME;
$previous   = get_option( $optionName, null );
$lastResult = get_option( SyncLogger::LAST_RESULT_OPTION, null );
$settings   = new Settings();
$repository = new PropertyRepository();
$logger     = new SyncLogger();
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

	$validAfterInvalid                = $payload[0];
	$validAfterInvalid['external_id'] = 'SYNC-TEST-1002';
	$validAfterInvalid['title']       = 'Valid property after invalid payload';
	$invalidProperty                  = $payload[0];
	unset( $invalidProperty['title'] );
	$payload = array( $invalidProperty, $validAfterInvalid );

	$invalid = $runner->run()->toArray();
	if ( 2 !== $invalid['processed'] || 1 !== $invalid['errors'] || 1 !== $invalid['created'] || null === $repository->findIdByExternalId( 'SYNC-TEST-1002' ) ) {
		throw new RuntimeException( 'An invalid property was not isolated from the next valid item.' );
	}

	$invalidLogged = false;
	foreach ( $logger->getRecent() as $record ) {
		if ( $invalid['run_id'] === $record['run_id'] && 'property_invalid' === $record['event'] && str_contains( (string) $record['message'], 'title' ) ) {
			$invalidLogged = true;
			break;
		}
	}
	if ( ! $invalidLogged ) {
		throw new RuntimeException( 'The invalid payload did not produce a useful safe log.' );
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

	$payload                         = array( $validAfterInvalid );
	$payload[0]['external_id']       = 'SYNC-TEST-1001';
	$payload[0]['title']             = 'Duplicate external ID payload';
	$persistenceFailure             = $runner->run()->toArray();
	if ( 1 !== $persistenceFailure['errors'] || 0 !== $persistenceFailure['created'] || 0 !== $persistenceFailure['updated'] ) {
		throw new RuntimeException( 'A known persistence failure was not isolated.' );
	}

	$persistenceLogged = false;
	foreach ( $logger->getRecent() as $record ) {
		if ( $persistenceFailure['run_id'] === $record['run_id'] && 'property_persistence_failed' === $record['event'] ) {
			$persistenceLogged = true;
			break;
		}
	}
	if ( ! $persistenceLogged ) {
		throw new RuntimeException( 'The persistence failure did not produce a useful safe log.' );
	}

	$payload                   = array( $validAfterInvalid );
	$payload[0]['external_id'] = 'SYNC-TEST-UNEXPECTED';
	$unexpectedFilter          = static function ( array $postData ): array {
		throw new TypeError( 'Unexpected programming error for smoke test.' );
	};
	$unexpectedPropagated      = false;

	add_filter( 'wp_insert_post_data', $unexpectedFilter );
	try {
		$runner->run();
	} catch ( TypeError $exception ) {
		$unexpectedPropagated = true;
	} finally {
		remove_filter( 'wp_insert_post_data', $unexpectedFilter );
	}

	if ( ! $unexpectedPropagated ) {
		throw new RuntimeException( 'An unexpected programming error was swallowed.' );
	}
	if ( null !== get_option( SyncLock::OPTION_NAME, null ) ) {
		throw new RuntimeException( 'The synchronization lock was not released after an unexpected error.' );
	}

	$failedResult = get_option( SyncLogger::LAST_RESULT_OPTION );
	if ( ! is_array( $failedResult ) || 'failed' !== ( $failedResult['status'] ?? null ) ) {
		throw new RuntimeException( 'An unexpected error did not mark the synchronization as failed.' );
	}

	$unexpectedLogged = false;
	foreach ( $logger->getRecent() as $record ) {
		if ( $failedResult['run_id'] === $record['run_id'] && 'sync_unexpected_failure' === $record['event'] && str_contains( (string) $record['context_json'], 'TypeError' ) ) {
			$unexpectedLogged = true;
			break;
		}
	}
	if ( ! $unexpectedLogged ) {
		throw new RuntimeException( 'The unexpected error did not produce a safe diagnostic log.' );
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
					'value'   => array( 'SYNC-TEST-1001', 'SYNC-TEST-1002', 'SYNC-TEST-UNEXPECTED' ),
					'compare' => 'IN',
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

	if ( null === $lastResult ) {
		delete_option( SyncLogger::LAST_RESULT_OPTION );
	} else {
		update_option( SyncLogger::LAST_RESULT_OPTION, $lastResult, false );
	}
}

echo "Property synchronization smoke test passed.\n";
