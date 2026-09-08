<?php
/**
 * Unit tests for PropertyNormalizer.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PropertySync\Sync\PropertyNormalizer;

final class PropertyNormalizerTest extends TestCase {

	public function testNormalizesValidProperty(): void {
		$normalized = ( new PropertyNormalizer() )->normalize( $this->property() );

		self::assertSame( 'PROP-1001', $normalized['external_id'] );
		self::assertSame( '485000.50', $normalized['price'] );
		self::assertSame( '78.00', $normalized['area'] );
		self::assertSame( '2026-09-01T14:30:00Z', $normalized['updated_at'] );
	}

	public function testRejectsMissingExternalId(): void {
		$property = $this->property();
		unset( $property['external_id'] );

		$this->expectException( InvalidArgumentException::class );
		( new PropertyNormalizer() )->normalize( $property );
	}

	public function testRejectsInvalidFieldTypesAndValues(): void {
		$cases = array(
			array( 'price', '-1.00' ),
			array( 'bedrooms', '2' ),
			array( 'image_url', 'javascript:alert(1)' ),
			array( 'updated_at', 'yesterday' ),
		);

		foreach ( $cases as [ $field, $value ] ) {
			$property           = $this->property();
			$property[ $field ] = $value;

			try {
				( new PropertyNormalizer() )->normalize( $property );
				self::fail( "Invalid {$field} was accepted." );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function property(): array {
		return array(
			'external_id'   => 'PROP-1001',
			'title'         => 'Property title',
			'description'   => 'Property description',
			'price'         => '485000.5',
			'property_type' => 'apartment',
			'city'          => 'Sao Paulo',
			'neighborhood'  => 'Pinheiros',
			'bedrooms'      => 2,
			'bathrooms'     => 1,
			'area'          => '78',
			'status'        => 'available',
			'image_url'     => 'https://example.com/image.jpg',
			'updated_at'    => '2026-09-01T11:30:00-03:00',
		);
	}
}
