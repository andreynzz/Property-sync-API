<?php
/**
 * Smoke checks for property normalization and deterministic hashing.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/property-normalization.php
 *
 * @package PropertySync
 */

use PropertySync\Sync\PropertyHasher;
use PropertySync\Sync\PropertyNormalizer;

$property = array(
	'external_id'  => ' PROP-1001 ',
	'title'        => ' Modern apartment downtown ',
	'description'  => 'Two-bedroom apartment close to public transport.',
	'price'        => '000485000.5',
	'property_type' => 'apartment',
	'city'         => 'Sao Paulo',
	'neighborhood' => 'Pinheiros',
	'bedrooms'     => 2,
	'bathrooms'    => 2,
	'area'         => '078.50',
	'status'       => 'available',
	'image_url'    => 'https://example.com/images/prop-1001.jpg',
	'updated_at'   => '2026-09-01T11:30:00-03:00',
	'extra_field'  => 'ignored',
);

$normalizer = new PropertyNormalizer();
$normalized = $normalizer->normalize( $property );

if ( 'PROP-1001' !== $normalized['external_id'] || '485000.50' !== $normalized['price'] || '78.50' !== $normalized['area'] || '2026-09-01T14:30:00Z' !== $normalized['updated_at'] || isset( $normalized['extra_field'] ) ) {
	throw new RuntimeException( 'Property normalization did not produce the expected canonical fields.' );
}

foreach ( array( 'external_id', 'price', 'image_url' ) as $missingField ) {
	$invalid = $property;
	unset( $invalid[ $missingField ] );

	try {
		$normalizer->normalize( $invalid );
		throw new RuntimeException( "Missing {$missingField} was accepted." );
	} catch ( InvalidArgumentException $exception ) {
	}
}

$invalidDecimal          = $property;
$invalidDecimal['price'] = '-1.00';
try {
	$normalizer->normalize( $invalidDecimal );
	throw new RuntimeException( 'A negative price was accepted.' );
} catch ( InvalidArgumentException $exception ) {
}

$hasher         = new PropertyHasher();
$sameContent    = $normalized;
$sameContent['updated_at'] = '2026-09-02T14:30:00Z';
$reordered      = array( 'nested' => array( 'b' => 2, 'a' => 1 ), 'title' => 'Example' );
$reorderedAgain = array( 'title' => 'Example', 'nested' => array( 'a' => 1, 'b' => 2 ) );

if ( $hasher->hash( $normalized ) !== $hasher->hash( $sameContent ) ) {
	throw new RuntimeException( 'Audit data changed the content hash.' );
}

if ( $hasher->hash( $reordered ) !== $hasher->hash( $reorderedAgain ) ) {
	throw new RuntimeException( 'Key order changed the content hash.' );
}

$changedContent          = $normalized;
$changedContent['title'] = 'Changed title';
if ( $hasher->hash( $normalized ) === $hasher->hash( $changedContent ) ) {
	throw new RuntimeException( 'Relevant content did not change the hash.' );
}

echo "Property normalization smoke test passed.\n";
