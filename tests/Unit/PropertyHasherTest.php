<?php
/**
 * Unit tests for PropertyHasher.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PropertySync\Sync\PropertyHasher;

final class PropertyHasherTest extends TestCase {

	public function testHashIsStableAcrossAssociativeKeyOrder(): void {
		$hasher = new PropertyHasher();

		self::assertSame(
			$hasher->hash(
				array(
					'title'   => 'Property',
					'details' => array(
						'city'     => 'Sao Paulo',
						'bedrooms' => 2,
					),
				)
			),
			$hasher->hash(
				array(
					'details' => array(
						'bedrooms' => 2,
						'city'     => 'Sao Paulo',
					),
					'title'   => 'Property',
				)
			)
		);
	}

	public function testAuditFieldsDoNotChangeTheHash(): void {
		$hasher = new PropertyHasher();
		$first  = array(
			'external_id' => 'PROP-1',
			'updated_at'  => '2026-09-01T00:00:00Z',
			'title'       => 'Property',
		);
		$second = array(
			'external_id' => 'PROP-2',
			'updated_at'  => '2026-09-02T00:00:00Z',
			'title'       => 'Property',
		);

		self::assertSame( $hasher->hash( $first ), $hasher->hash( $second ) );
	}

	public function testRelevantContentChangesTheHash(): void {
		$hasher = new PropertyHasher();

		self::assertNotSame(
			$hasher->hash( array( 'title' => 'First title' ) ),
			$hasher->hash( array( 'title' => 'Updated title' ) )
		);
	}
}
