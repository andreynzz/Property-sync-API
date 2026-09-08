<?php
/**
 * Orchestrates a complete property synchronization run.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Sync;

use PropertySync\Logging\SyncLogger;
use PropertySync\Api\PropertyApiClient;
use Throwable;

final class SyncRunner
{
	private PropertyApiClient $apiClient;

	private PropertyNormalizer $normalizer;

	private PropertyHasher $hasher;

	private PropertyRepository $repository;

	private SyncLogger $logger;

	private SyncLock $lock;

	public function __construct(
		?PropertyApiClient $apiClient = null,
		?PropertyNormalizer $normalizer = null,
		?PropertyHasher $hasher = null,
		?PropertyRepository $repository = null,
		?SyncLogger $logger = null,
		?SyncLock $lock = null
	)
	{
		$this->apiClient   = $apiClient ?? new PropertyApiClient();
		$this->normalizer  = $normalizer ?? new PropertyNormalizer();
		$this->hasher      = $hasher ?? new PropertyHasher();
		$this->repository  = $repository ?? new PropertyRepository();
		$this->logger      = $logger ?? new SyncLogger();
		$this->lock        = $lock ?? new SyncLock();
	}

	/**
	 * Run a complete sync. A transport or API-level error stops the run, while
	 * one malformed property is counted and does not block the remaining items.
	 */
	public function run( string $source = 'manual' ): SyncResult
	{
		$result = new SyncResult( wp_generate_uuid4(), gmdate( 'Y-m-d\TH:i:s\Z' ) );
		$runId  = $result->getRunId();
		$token  = $this->lock->acquire( $source );

		if ( null === $token ) {
			$result->markAlreadyRunning();
			$result->finish( gmdate( 'Y-m-d\TH:i:s\Z' ) );
			$this->logger->log( $runId, 'INFO', 'sync_already_running', 'Property synchronization was already running.' );

			return $result;
		}

		$this->logger->log( $runId, 'INFO', 'sync_started', 'Property synchronization started.' );

		try {
			foreach ( $this->apiClient->fetchProperties() as $property ) {
				$result->incrementProcessed();
				$this->syncProperty( $property, $result );
			}
		} catch ( Throwable $exception ) {
			$this->logger->log( $runId, 'ERROR', 'sync_failed', 'Property synchronization stopped because the API could not be read.' );
			throw $exception;
		} finally {
			$result->finish( gmdate( 'Y-m-d\TH:i:s\Z' ) );

			try {
				$this->logger->storeLastResult( $result );
				$this->logger->log( $runId, 'INFO', 'sync_finished', 'Property synchronization finished.' );
				$this->logger->prune();
			} finally {
				$this->lock->release( $token );
			}
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
				$this->logger->log( $result->getRunId(), 'CREATED', 'property_created', 'Property created.', $normalized['external_id'] );
				return;
			}

			if ( hash_equals( $this->repository->getHash( $postId ), $hash ) ) {
				$result->incrementSkipped();
				$this->logger->log( $result->getRunId(), 'SKIPPED', 'property_skipped', 'Property unchanged.', $normalized['external_id'] );
				return;
			}

			$this->repository->update( $postId, $normalized, $hash );
			$result->incrementUpdated();
			$this->logger->log( $result->getRunId(), 'UPDATED', 'property_updated', 'Property updated.', $normalized['external_id'] );
		} catch ( Throwable $exception ) {
			$result->incrementErrors();
			$externalId = isset( $property['external_id'] ) && is_string( $property['external_id'] ) ? $property['external_id'] : null;
			$this->logger->log( $result->getRunId(), 'ERROR', 'property_failed', 'Property could not be synchronized.', $externalId );
		}
	}
}
