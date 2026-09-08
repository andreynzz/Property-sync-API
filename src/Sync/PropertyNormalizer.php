<?php
/**
 * Converts external property payloads into a validated canonical structure.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Sync;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

final class PropertyNormalizer
{
	/**
	 * Validate and normalize one external property.
	 *
	 * @param array<string, mixed> $property External API property payload.
	 * @return array{external_id: string, title: string, description: string, price: string, property_type: string, city: string, neighborhood: string, bedrooms: int, bathrooms: int, area: string, status: string, image_url: string, updated_at: string}
	 * @throws InvalidArgumentException When a required field is missing or invalid.
	 */
	public function normalize( array $property ): array
	{
		return array(
			'external_id'  => $this->requiredText( $property, 'external_id' ),
			'title'        => $this->requiredText( $property, 'title' ),
			'description'  => $this->requiredText( $property, 'description' ),
			'price'        => $this->decimal( $property, 'price' ),
			'property_type' => $this->requiredText( $property, 'property_type' ),
			'city'         => $this->requiredText( $property, 'city' ),
			'neighborhood' => $this->requiredText( $property, 'neighborhood' ),
			'bedrooms'     => $this->nonNegativeInteger( $property, 'bedrooms' ),
			'bathrooms'    => $this->nonNegativeInteger( $property, 'bathrooms' ),
			'area'         => $this->decimal( $property, 'area' ),
			'status'       => $this->requiredText( $property, 'status' ),
			'image_url'    => $this->url( $property, 'image_url' ),
			'updated_at'   => $this->dateTime( $property, 'updated_at' ),
		);
	}

	/**
	 * Read a required non-empty string and normalize surrounding whitespace.
	 *
	 * @param array<string, mixed> $property External API property payload.
	 */
	private function requiredText( array $property, string $field ): string
	{
		$value = $property[ $field ] ?? null;
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			throw new InvalidArgumentException( sprintf( 'Property field "%s" must be a non-empty string.', $field ) );
		}

		return trim( $value );
	}

	/**
	 * Read a non-negative decimal with up to two fractional digits.
	 *
	 * @param array<string, mixed> $property External API property payload.
	 */
	private function decimal( array $property, string $field ): string
	{
		$value = $property[ $field ] ?? null;
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d+(?:\.\d{1,2})?$/', $value ) ) {
			throw new InvalidArgumentException( sprintf( 'Property field "%s" must be a non-negative decimal string.', $field ) );
		}

		$parts   = explode( '.', $value, 2 );
		$integer = ltrim( $parts[0], '0' );
		$decimal = $parts[1] ?? '';

		return ( '' === $integer ? '0' : $integer ) . '.' . str_pad( $decimal, 2, '0' );
	}

	/**
	 * Read a non-negative JSON integer.
	 *
	 * @param array<string, mixed> $property External API property payload.
	 */
	private function nonNegativeInteger( array $property, string $field ): int
	{
		$value = $property[ $field ] ?? null;
		if ( ! is_int( $value ) || $value < 0 ) {
			throw new InvalidArgumentException( sprintf( 'Property field "%s" must be a non-negative integer.', $field ) );
		}

		return $value;
	}

	/**
	 * Read an absolute HTTP(S) image URL.
	 *
	 * @param array<string, mixed> $property External API property payload.
	 */
	private function url( array $property, string $field ): string
	{
		$value  = $property[ $field ] ?? null;
		$scheme = is_string( $value ) ? (string) parse_url( $value, PHP_URL_SCHEME ) : '';

		if ( ! is_string( $value ) || false === filter_var( $value, FILTER_VALIDATE_URL ) || ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
			throw new InvalidArgumentException( sprintf( 'Property field "%s" must be an absolute HTTP(S) URL.', $field ) );
		}

		return $value;
	}

	/**
	 * Parse an ISO 8601 timestamp and store it as UTC.
	 *
	 * @param array<string, mixed> $property External API property payload.
	 */
	private function dateTime( array $property, string $field ): string
	{
		$value = $property[ $field ] ?? null;
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value ) ) {
			throw new InvalidArgumentException( sprintf( 'Property field "%s" must be an ISO 8601 timestamp.', $field ) );
		}

		try {
			$dateTime = new DateTimeImmutable( $value );
		} catch ( Throwable $exception ) {
			throw new InvalidArgumentException( sprintf( 'Property field "%s" must be a valid timestamp.', $field ), 0, $exception );
		}

		return $dateTime->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
	}
}
