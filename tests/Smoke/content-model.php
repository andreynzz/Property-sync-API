<?php
/**
 * Smoke checks for the registered property content model.
 *
 * Run with: docker compose run --rm wpcli wp eval-file
 * wp-content/plugins/property-sync/tests/Smoke/content-model.php
 *
 * @package PropertySync
 */

use PropertySync\PostType\PropertyPostType;

if ( ! post_type_exists( PropertyPostType::POST_TYPE ) ) {
	throw new RuntimeException( 'The property post type is not registered.' );
}

$expected_taxonomies = array(
	PropertyPostType::TYPE_TAXONOMY,
	PropertyPostType::CITY_TAXONOMY,
	PropertyPostType::STATUS_TAXONOMY,
);
$registered_taxonomies = get_object_taxonomies( PropertyPostType::POST_TYPE );

foreach ( $expected_taxonomies as $taxonomy ) {
	if ( ! in_array( $taxonomy, $registered_taxonomies, true ) ) {
		throw new RuntimeException( "The {$taxonomy} taxonomy is not registered for properties." );
	}
}

$expected_meta = array(
	'_property_external_id',
	'_property_price',
	'_property_neighborhood',
	'_property_bedrooms',
	'_property_bathrooms',
	'_property_area',
	'_property_image_url',
	'_property_external_updated_at',
	'_property_sync_hash',
	'_property_last_synced_at',
);
$registered_meta = get_registered_meta_keys( 'post', PropertyPostType::POST_TYPE );

foreach ( $expected_meta as $meta_key ) {
	if ( ! isset( $registered_meta[ $meta_key ] ) ) {
		throw new RuntimeException( "The {$meta_key} metadata field is not registered." );
	}
}

$post_type = get_post_type_object( PropertyPostType::POST_TYPE );

if ( ! $post_type || ! $post_type->public || ! $post_type->show_in_rest || ! $post_type->has_archive ) {
	throw new RuntimeException( 'The property post type is not publicly queryable as expected.' );
}

if ( '12.50' !== sanitize_meta( '_property_price', '0012.5', 'post', PropertyPostType::POST_TYPE ) ) {
	throw new RuntimeException( 'Decimal metadata sanitization failed.' );
}

if ( '' !== sanitize_meta( '_property_price', '-10', 'post', PropertyPostType::POST_TYPE ) ) {
	throw new RuntimeException( 'Negative decimal metadata was not rejected.' );
}

$hash = str_repeat( 'a', 64 );

if ( $hash !== sanitize_meta( '_property_sync_hash', strtoupper( $hash ), 'post', PropertyPostType::POST_TYPE ) ) {
	throw new RuntimeException( 'Hash metadata sanitization failed.' );
}
echo "Property content model smoke test passed.\n";
