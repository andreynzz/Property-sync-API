<?php
/**
 * Produces a stable fingerprint for synchronized property content.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Sync;

use JsonException;

final class PropertyHasher {

	/**
	 * Hash fields that change published property content.
	 *
	 * The external identifier selects the record, while updated_at is audit data;
	 * neither should cause an otherwise unchanged property to be updated.
	 *
	 * @param array<string, mixed> $property Normalized property payload.
	 * @throws JsonException When the supplied data cannot be encoded.
	 */
	public function hash( array $property ): string {
		unset( $property['external_id'], $property['updated_at'] );

		$json = json_encode(
			$this->sortRecursively( $property ),
			JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return hash( 'sha256', $json );
	}

	/**
	 * Sort associative keys recursively while preserving ordered lists.
	 *
	 * @param array<mixed> $value Values to normalize before JSON encoding.
	 * @return array<mixed>
	 */
	private function sortRecursively( array $value ): array {
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = $this->sortRecursively( $item );
			}
		}

		if ( ! array_is_list( $value ) ) {
			ksort( $value, SORT_STRING );
		}

		return $value;
	}
}
