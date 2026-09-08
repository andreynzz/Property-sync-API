<?php
/**
 * Orchestrates a complete property synchronization run.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Sync;

use PropertySync\Api\PropertyApiClient;
use Throwable;

final class SyncRunner
{
	private PropertyApiClient $apiClient;

	private PropertyNormalizer $normalizer;

	private PropertyHasher $hasher;

	private PropertyRepository $repository;

	public function __construct(
		?PropertyApiClient $apiClient = null,
		?PropertyNormalizer $normalizer = null,
		?PropertyHasher $hasher = null,
		?PropertyRepository $repository = null
	)
	{
		$this->apiClient   = $apiClient ?? new PropertyApiClient();
		$this->normalizer  = $normalizer ?? new PropertyNormalizer();
		$this->hasher      = $hasher ?? new PropertyHasher();
		$this->repository  = $repository ?? new PropertyRepository();
	}

	/**
	 * Run a complete sync. A transport or API-level error stops the run, while
	 * one malformed property is counted and does not block the remaining items.
	 */
	public function run(): SyncResult
	{
		$result = new SyncResult( wp_generate_uuid4(), gmdate( 'Y-m-d\TH:i:s\Z' ) );

		try {
			foreach ( $this->apiClient->fetchProperties() as $property ) {
				$result->incrementProcessed();
				$this->syncProperty( $property, $result );
			}
		} finally {
			$result->finish( gmdate( 'Y-m-d\TH:i:s\Z' ) );
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $property External property payload.
	 */
	private function syncProperty( array $property, SyncResult $result ): void
	{
		try {
			$normalized = $this->normalizer->normalize( $property );
			$hash       = $this->hasher->hash( $normalized );
			$postId     = $this->repository->findIdByExternalId( $normalized['external_id'] );

			if ( null === $postId ) {
				$this->repository->create( $normalized, $hash );
				$result->incrementCreated();
				return;
			}

			if ( hash_equals( $this->repository->getHash( $postId ), $hash ) ) {
				$result->incrementSkipped();
				return;
			}

			$this->repository->update( $postId, $normalized, $hash );
			$result->incrementUpdated();
		} catch ( Throwable $exception ) {
			$result->incrementErrors();
		}
	}
}
