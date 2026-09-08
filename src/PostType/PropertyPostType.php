<?php
/**
 * Property content model registration.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\PostType;

final class PropertyPostType {

	public const POST_TYPE = 'property';

	public const TYPE_TAXONOMY = 'property_type';

	public const CITY_TAXONOMY = 'property_city';

	public const STATUS_TAXONOMY = 'property_status';

	/**
	 * Register the content model on WordPress init.
	 */
	public function registerHooks(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the post type, taxonomies, and metadata.
	 */
	public function register(): void {
		$this->registerPostType();
		$this->registerTaxonomies();
		$this->registerMeta();
	}

	/**
	 * Register the property custom post type.
	 */
	private function registerPostType(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Properties', 'property-sync' ),
					'singular_name'         => __( 'Property', 'property-sync' ),
					'add_new'               => __( 'Add New', 'property-sync' ),
					'add_new_item'          => __( 'Add New Property', 'property-sync' ),
					'edit_item'             => __( 'Edit Property', 'property-sync' ),
					'new_item'              => __( 'New Property', 'property-sync' ),
					'view_item'             => __( 'View Property', 'property-sync' ),
					'view_items'            => __( 'View Properties', 'property-sync' ),
					'search_items'          => __( 'Search Properties', 'property-sync' ),
					'not_found'             => __( 'No properties found.', 'property-sync' ),
					'not_found_in_trash'    => __( 'No properties found in Trash.', 'property-sync' ),
					'all_items'             => __( 'All Properties', 'property-sync' ),
					'archives'              => __( 'Property Archives', 'property-sync' ),
					'attributes'            => __( 'Property Attributes', 'property-sync' ),
					'insert_into_item'      => __( 'Insert into property', 'property-sync' ),
					'uploaded_to_this_item' => __( 'Uploaded to this property', 'property-sync' ),
					'menu_name'             => __( 'Properties', 'property-sync' ),
				),
				'public'              => true,
				'show_in_rest'        => true,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'properties' ),
				'menu_icon'           => 'dashicons-building',
				'menu_position'       => 20,
				'supports'            => array( 'title', 'editor', 'thumbnail' ),
				'taxonomies'          => array(
					self::TYPE_TAXONOMY,
					self::CITY_TAXONOMY,
					self::STATUS_TAXONOMY,
				),
				'delete_with_user'    => false,
				'show_in_nav_menus'   => true,
				'exclude_from_search' => false,
			)
		);
	}

	/**
	 * Register property taxonomies.
	 */
	private function registerTaxonomies(): void {
		register_taxonomy(
			self::TYPE_TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Property Types', 'property-sync' ),
					'singular_name' => __( 'Property Type', 'property-sync' ),
					'search_items'  => __( 'Search Property Types', 'property-sync' ),
					'all_items'     => __( 'All Property Types', 'property-sync' ),
					'edit_item'     => __( 'Edit Property Type', 'property-sync' ),
					'update_item'   => __( 'Update Property Type', 'property-sync' ),
					'add_new_item'  => __( 'Add New Property Type', 'property-sync' ),
					'new_item_name' => __( 'New Property Type Name', 'property-sync' ),
					'menu_name'     => __( 'Property Types', 'property-sync' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'property-type' ),
			)
		);

		register_taxonomy(
			self::CITY_TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Cities', 'property-sync' ),
					'singular_name' => __( 'City', 'property-sync' ),
					'search_items'  => __( 'Search Cities', 'property-sync' ),
					'all_items'     => __( 'All Cities', 'property-sync' ),
					'edit_item'     => __( 'Edit City', 'property-sync' ),
					'update_item'   => __( 'Update City', 'property-sync' ),
					'add_new_item'  => __( 'Add New City', 'property-sync' ),
					'new_item_name' => __( 'New City Name', 'property-sync' ),
					'menu_name'     => __( 'Cities', 'property-sync' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'property-city' ),
			)
		);

		register_taxonomy(
			self::STATUS_TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Property Statuses', 'property-sync' ),
					'singular_name' => __( 'Property Status', 'property-sync' ),
					'search_items'  => __( 'Search Property Statuses', 'property-sync' ),
					'all_items'     => __( 'All Property Statuses', 'property-sync' ),
					'edit_item'     => __( 'Edit Property Status', 'property-sync' ),
					'update_item'   => __( 'Update Property Status', 'property-sync' ),
					'add_new_item'  => __( 'Add New Property Status', 'property-sync' ),
					'new_item_name' => __( 'New Property Status Name', 'property-sync' ),
					'menu_name'     => __( 'Statuses', 'property-sync' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'property-status' ),
			)
		);
	}

	/**
	 * Register internal metadata with explicit storage types and sanitization.
	 */
	private function registerMeta(): void {
		$fields = array(
			'_property_external_id'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_property_price'               => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitizeDecimal' ),
			),
			'_property_neighborhood'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_property_bedrooms'            => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'_property_bathrooms'           => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'_property_area'                => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitizeDecimal' ),
			),
			'_property_image_url'           => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'_property_external_updated_at' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_property_sync_hash'           => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitizeHash' ),
			),
			'_property_last_synced_at'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		foreach ( $fields as $key => $args ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array_merge(
					$args,
					array(
						'single'       => true,
						'show_in_rest' => false,
					)
				)
			);
		}
	}

	/**
	 * Normalize a non-negative decimal to two fractional digits.
	 *
	 * @param mixed $value Raw metadata value.
	 */
	public function sanitizeDecimal( mixed $value ): string {
		$value = trim( (string) $value );

		if ( 1 !== preg_match( '/^\d+(?:\.\d{1,2})?$/', $value ) ) {
			return '';
		}

		$parts   = explode( '.', $value, 2 );
		$integer = ltrim( $parts[0], '0' );
		$decimal = $parts[1] ?? '';

		return ( '' === $integer ? '0' : $integer ) . '.' . str_pad( $decimal, 2, '0' );
	}

	/**
	 * Accept only a complete SHA-256 hash.
	 *
	 * @param mixed $value Raw metadata value.
	 */
	public function sanitizeHash( mixed $value ): string {
		$value = strtolower( trim( (string) $value ) );

		return 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}
}
