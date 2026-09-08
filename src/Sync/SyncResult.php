<?php
/**
 * Immutable-identification result counters for one synchronization run.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Sync;

use DateTimeImmutable;

final class SyncResult
{
	private string $runId;

	private string $startedAt;

	private ?string $finishedAt = null;

	private int $processed = 0;

	private int $created = 0;

	private int $updated = 0;

	private int $skipped = 0;

	private int $errors = 0;

	private string $status = 'completed';

	public function __construct( string $runId, string $startedAt )
	{
		$this->runId     = $runId;
		$this->startedAt = $startedAt;
	}

	public function incrementProcessed(): void
	{
		++$this->processed;
	}

	public function incrementCreated(): void
	{
		++$this->created;
	}

	public function incrementUpdated(): void
	{
		++$this->updated;
	}

	public function incrementSkipped(): void
	{
		++$this->skipped;
	}

	public function incrementErrors(): void
	{
		++$this->errors;
	}

	public function getRunId(): string
	{
		return $this->runId;
	}

	public function markAlreadyRunning(): void
	{
		$this->status = 'already_running';
	}

	public function finish( string $finishedAt ): void
	{
		$this->finishedAt = $finishedAt;
	}

	/**
	 * @return array{run_id: string, started_at: string, finished_at: string|null, duration: int|null, status: string, processed: int, created: int, updated: int, skipped: int, errors: int}
	 */
	public function toArray(): array
	{
		return array(
			'run_id'      => $this->runId,
			'started_at'  => $this->startedAt,
			'finished_at' => $this->finishedAt,
			'duration'    => $this->duration(),
			'status'      => $this->status,
			'processed'   => $this->processed,
			'created'     => $this->created,
			'updated'     => $this->updated,
			'skipped'     => $this->skipped,
			'errors'      => $this->errors,
		);
	}

	/**
	 * Get elapsed whole seconds once the run has finished.
	 */
	private function duration(): ?int
	{
		if ( null === $this->finishedAt ) {
			return null;
		}

		$startedAt  = new DateTimeImmutable( $this->startedAt );
		$finishedAt = new DateTimeImmutable( $this->finishedAt );

		return $finishedAt->getTimestamp() - $startedAt->getTimestamp();
	}
}
