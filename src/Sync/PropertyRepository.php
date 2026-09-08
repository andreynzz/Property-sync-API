<?php
/**
 * WordPress persistence for normalized property records.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Sync;

use PropertySync\PostType\PropertyPostType;
use RuntimeException;
use WP_Error;

final class PropertyRepository
{
	/**
	 * Find one property by its externally-owned identifier.
	 *
	 * @throws RuntimeException When an integrity violation produces duplicate records.
	 */
	public function findIdByExternalId( string $externalId ): ?int
	{
		$postIds = get_posts(
			array(
				'post_type'              => PropertyPostType::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 2,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'ignore_sticky_posts'    => true,
				'meta_query'             => array(
					array(
						'key'     => '_property_external_id',
						'value'   => $externalId,
						'compare' => '=',
					),
				),
			)
		);

		if ( count( $postIds ) > 1 ) {
			throw new RuntimeException( 'Multiple properties share the same external ID.' );
		}

		return isset( $postIds[0] ) ? (int) $postIds[0] : null;
	}

	/**
	 * Create a published property and persist its synchronized fields.
	 *
	 * @param array<string, mixed> $property Normalized property data.
	 * @throws RuntimeException When WordPress cannot persist the property.
	 */
	public function create( array $property, string $hash ): int
	{
		$postId = wp_insert_post( $this->postData( $property ), true );
		if ( $postId instanceof WP_Error ) {
			throw new RuntimeException( 'Unable to create property.', 0, $postId );
		}

		$this->persistFields( $postId, $property, $hash );

		return $postId;
	}

	/**
	 * Update a synchronized property and its fields.
	 *
	 * @param array<string, mixed> $property Normalized property data.
	 * @throws RuntimeException When WordPress cannot persist the property.
	 */
	public function update( int $postId, array $property, string $hash ): void
	{
		$postData     = $this->postData( $property );
		$postData['ID'] = $postId;
		$result       = wp_update_post( $postData, true );

		if ( $result instanceof WP_Error ) {
			throw new RuntimeException( 'Unable to update property.', 0, $result );
		}

		$this->persistFields( $postId, $property, $hash );
	}

	/**
	 * Get the previously persisted content hash.
	 */
	public function getHash( int $postId ): string
	{
		return (string) get_post_meta( $postId, '_property_sync_hash', true );
	}

	/**
	 * @param array<string, mixed> $property Normalized property data.
	 * @return array<string, mixed>
	 */
	private function postData( array $property ): array
	{
		return array(
			'post_type'    => PropertyPostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => (string) $property['title'],
			'post_content' => (string) $property['description'],
		);
	}

	/**
	 * @param array<string, mixed> $property Normalized property data.
	 * @throws RuntimeException When metadata or terms cannot be saved.
	 */
	private function persistFields( int $postId, array $property, string $hash ): void
	{
		$metadata = array(
			'_property_external_id'         => $property['external_id'],
			'_property_price'               => $property['price'],
			'_property_neighborhood'        => $property['neighborhood'],
			'_property_bedrooms'            => $property['bedrooms'],
			'_property_bathrooms'           => $property['bathrooms'],
			'_property_area'                => $property['area'],
			'_property_image_url'           => $property['image_url'],
			'_property_external_updated_at' => $property['updated_at'],
			'_property_sync_hash'           => $hash,
			'_property_last_synced_at'      => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);

		foreach ( $metadata as $key => $value ) {
			if ( false === update_post_meta( $postId, $key, $value ) ) {
				$existing = get_post_meta( $postId, $key, true );
				if ( (string) $existing !== (string) $value ) {
					throw new RuntimeException( 'Unable to save property metadata.' );
				}
			}
		}

		$this->assignTerm( $postId, PropertyPostType::TYPE_TAXONOMY, (string) $property['property_type'] );
		$this->assignTerm( $postId, PropertyPostType::CITY_TAXONOMY, (string) $property['city'] );
		$this->assignTerm( $postId, PropertyPostType::STATUS_TAXONOMY, (string) $property['status'] );
	}

	/**
	 * @throws RuntimeException When a taxonomy assignment fails.
	 */
	private function assignTerm( int $postId, string $taxonomy, string $term ): void
	{
		$result = wp_set_object_terms( $postId, array( $term ), $taxonomy, false );
		if ( $result instanceof WP_Error ) {
			throw new RuntimeException( 'Unable to assign property taxonomy terms.', 0, $result );
		}
	}
}
