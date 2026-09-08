<?php
/**
 * Option-backed mutual exclusion for synchronization runs.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Sync;

final class SyncLock
{
	public const OPTION_NAME = 'property_sync_lock';

	private const TTL_SECONDS = 900;

	/**
	 * Acquire the lock and return its ownership token, or null if another run owns it.
	 */
	public function acquire( string $source ): ?string
	{
		$payload = $this->payload( $source );

		if ( add_option( self::OPTION_NAME, $payload, '', false ) ) {
			return $payload['token'];
		}

		$current = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $current ) || ! $this->isExpired( $current ) ) {
			return null;
		}

		return $this->replaceExpiredLock( $current, $payload ) ? $payload['token'] : null;
	}

	/**
	 * Release the lock only when the caller still owns the matching token.
	 */
	public function release( string $token ): void
	{
		$current = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $current ) || ! isset( $current['token'] ) || ! hash_equals( (string) $current['token'], $token ) ) {
			return;
		}

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::OPTION_NAME,
				maybe_serialize( $current )
			)
		);
		wp_cache_delete( self::OPTION_NAME, 'options' );
	}

	/**
	 * @return array{token: string, started_at: int, source: string}
	 */
	private function payload( string $source ): array
	{
		return array(
			'token'      => wp_generate_uuid4(),
			'started_at' => time(),
			'source'     => sanitize_key( $source ),
		);
	}

	/**
	 * @param array<string, mixed> $lock Existing lock value.
	 */
	private function isExpired( array $lock ): bool
	{
		return ! isset( $lock['started_at'] ) || ! is_int( $lock['started_at'] ) || $lock['started_at'] <= time() - self::TTL_SECONDS;
	}

	/**
	 * Compare and replace the stale value so a concurrent recovery cannot be deleted.
	 *
	 * @param array<string, mixed> $current Existing stale payload.
	 * @param array{token: string, started_at: int, source: string} $replacement New payload.
	 */
	private function replaceExpiredLock( array $current, array $replacement ): bool
	{
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				maybe_serialize( $replacement ),
				self::OPTION_NAME,
				maybe_serialize( $current )
			)
		);

		if ( 1 !== $updated ) {
			return false;
		}

		wp_cache_delete( self::OPTION_NAME, 'options' );

		return true;
	}
}
