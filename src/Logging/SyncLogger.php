<?php
/**
 * Structured storage and retrieval for synchronization events.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Logging;

use PropertySync\Sync\SyncResult;

final class SyncLogger
{
	public const LAST_RESULT_OPTION = 'property_sync_last_result';

	private const MAX_ROWS = 5000;

	private const RETENTION_DAYS = 30;

	/**
	 * Store a non-sensitive event. Logging failures intentionally do not break sync.
	 *
	 * @param array<string, mixed> $context Safe diagnostic context.
	 */
	public function log( string $runId, string $level, string $event, string $message, ?string $externalId = null, array $context = array() ): void
	{
		global $wpdb;

		$wpdb->insert(
			$this->tableName(),
			array(
				'run_id'       => sanitize_text_field( $runId ),
				'level'        => $this->level( $level ),
				'event'        => sanitize_key( $event ),
				'external_id'  => null === $externalId ? null : sanitize_text_field( $externalId ),
				'message'      => wp_strip_all_tags( $message ),
				'context_json' => $this->contextJson( $context ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Persist the last completed result without autoloading it on every request.
	 */
	public function storeLastResult( SyncResult $result ): void
	{
		$summary = $result->toArray();

		if ( false === get_option( self::LAST_RESULT_OPTION, false ) ) {
			add_option( self::LAST_RESULT_OPTION, $summary, '', false );
			return;
		}

		update_option( self::LAST_RESULT_OPTION, $summary, false );
	}

	/**
	 * Fetch the newest log records for a future dashboard.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function getRecent( int $limit = 50 ): array
	{
		global $wpdb;

		$limit = max( 1, min( 50, $limit ) );
		$query = $wpdb->prepare( "SELECT id, run_id, level, event, external_id, message, context_json, created_at FROM {$this->tableName()} ORDER BY id DESC LIMIT %d", $limit );

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Retain at most 30 days and 5,000 newest events.
	 */
	public function prune(): void
	{
		global $wpdb;

		$tableName = $this->tableName();
		$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tableName} WHERE created_at < %s", $cutoff ) );

		$oldestKeptId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tableName} ORDER BY id DESC LIMIT 1 OFFSET %d",
				self::MAX_ROWS - 1
			)
		);

		if ( null === $oldestKeptId ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tableName} WHERE id < %d", (int) $oldestKeptId ) );
	}

	private function tableName(): string
	{
		global $wpdb;

		return $wpdb->prefix . 'property_sync_logs';
	}

	private function level( string $level ): string
	{
		$level = strtoupper( $level );

		return in_array( $level, array( 'INFO', 'CREATED', 'UPDATED', 'SKIPPED', 'ERROR' ), true ) ? $level : 'ERROR';
	}

	/**
	 * @param array<string, mixed> $context Potentially sensitive context.
	 */
	private function contextJson( array $context ): ?string
	{
		if ( array() === $context ) {
			return null;
		}

		return wp_json_encode( $this->sanitizeContext( $context ) );
	}

	/**
	 * Redact credential-shaped keys recursively before storage.
	 *
	 * @param array<string, mixed> $context Context values.
	 * @return array<string, mixed>
	 */
	private function sanitizeContext( array $context ): array
	{
		$sanitized = array();

		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || 1 === preg_match( '/token|authorization|secret|password|cookie/i', $key ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitizeContext( $value );
				continue;
			}

			if ( is_scalar( $value ) || null === $value ) {
				$sanitized[ $key ] = is_string( $value ) ? substr( wp_strip_all_tags( $value ), 0, 1000 ) : $value;
			}
		}

		return $sanitized;
	}
}
