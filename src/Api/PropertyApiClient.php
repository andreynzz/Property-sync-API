<?php
/**
 * Authenticated, paginated property API client.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Api;

use JsonException;
use PropertySync\Admin\Settings;
use WP_Error;

final class PropertyApiClient
{
	private const PER_PAGE = 50;

	private const MAX_PAGES = 100;

	private Settings $settings;

	/**
	 * @param Settings|null $settings Connection settings source.
	 */
	public function __construct( ?Settings $settings = null )
	{
		$this->settings = $settings ?? new Settings();
	}

	/**
	 * Fetch every property page, rejecting malformed or unexpectedly large responses.
	 *
	 * @return list<array<string, mixed>>
	 * @throws ApiException When the API cannot be read safely.
	 */
	public function fetchProperties(): array
	{
		$apiUrl = $this->settings->get()['api_url'];
		$token  = $this->settings->getApiToken();

		if ( '' === $apiUrl ) {
			throw new ApiException( __( 'Property API URL is not configured.', 'property-sync' ) );
		}

		if ( '' === $token ) {
			throw new ApiException( __( 'Property API token is not configured.', 'property-sync' ) );
		}

		$properties = array();
		$page       = 1;

		while ( $page <= self::MAX_PAGES ) {
			$response = $this->requestPage( $apiUrl, $token, $page );
			$data     = $response['data'];
			$meta     = $response['meta'];

			foreach ( $data as $property ) {
				$properties[] = $property;
			}

			if ( null === $meta['next_page'] ) {
				return $properties;
			}

			$page = $meta['next_page'];
		}

		throw new ApiException( __( 'Property API exceeded the configured page limit.', 'property-sync' ) );
	}

	/**
	 * Fetch and validate one page.
	 *
	 * @return array{data: list<array<string, mixed>>, meta: array{next_page: int|null}}
	 * @throws ApiException When the request or contract is invalid.
	 */
	private function requestPage( string $apiUrl, string $token, int $page ): array
	{
		$url      = add_query_arg(
			array(
				'page'     => $page,
				'per_page' => self::PER_PAGE,
			),
			$apiUrl
		);
		$response = $this->sendRequest( $url, $token );

		if ( $response instanceof WP_Error ) {
			throw new ApiException( __( 'Property API request failed.', 'property-sync' ) );
		}

		$statusCode = wp_remote_retrieve_response_code( $response );
		if ( $statusCode < 200 || $statusCode >= 300 ) {
			throw new ApiException(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Property API returned HTTP status %d.', 'property-sync' ),
					$statusCode
				)
			);
		}

		try {
			$payload = json_decode( wp_remote_retrieve_body( $response ), true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new ApiException( __( 'Property API returned invalid JSON.', 'property-sync' ), 0, $exception );
		}

		return $this->validatePagePayload( $payload, $page );
	}

	/**
	 * Send a request through the safe WordPress HTTP client, except for the Docker-only mock host.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function sendRequest( string $url, string $token ): array|WP_Error
	{
		$args = array(
			'timeout'             => 15,
			'redirection'         => 3,
			'reject_unsafe_urls'  => true,
			'headers'             => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
		);

		if ( $this->isDockerMockUrl( $url ) ) {
			// The Docker service name is intentionally not public DNS, so safe remote
			// requests reject it. This exception remains limited to local environments.
			$args['reject_unsafe_urls'] = false;
			return wp_remote_get( $url, $args );
		}

		return wp_safe_remote_get( $url, $args );
	}

	/**
	 * Validate the pagination envelope before it reaches synchronization logic.
	 *
	 * @param mixed $payload Decoded JSON body.
	 * @return array{data: list<array<string, mixed>>, meta: array{next_page: int|null}}
	 * @throws ApiException When the API contract is invalid.
	 */
	private function validatePagePayload( mixed $payload, int $requestedPage ): array
	{
		if ( ! is_array( $payload ) || ! isset( $payload['data'], $payload['meta'] ) || ! is_array( $payload['data'] ) || ! is_array( $payload['meta'] ) ) {
			throw new ApiException( __( 'Property API returned an invalid response contract.', 'property-sync' ) );
		}

		$meta = $payload['meta'];
		if ( ! $this->isPositiveInteger( $meta['page'] ?? null ) || ! $this->isPositiveInteger( $meta['per_page'] ?? null ) || ! $this->isNonNegativeInteger( $meta['total'] ?? null ) || ! $this->isPositiveInteger( $meta['total_pages'] ?? null ) || (int) $meta['page'] !== $requestedPage ) {
			throw new ApiException( __( 'Property API returned invalid pagination metadata.', 'property-sync' ) );
		}

		$nextPage = $meta['next_page'] ?? null;
		if ( null !== $nextPage && ( ! $this->isPositiveInteger( $nextPage ) || (int) $nextPage <= $requestedPage || (int) $nextPage > (int) $meta['total_pages'] ) ) {
			throw new ApiException( __( 'Property API returned an invalid next page.', 'property-sync' ) );
		}

		$data = array();
		foreach ( $payload['data'] as $property ) {
			if ( ! is_array( $property ) ) {
				throw new ApiException( __( 'Property API returned an invalid property item.', 'property-sync' ) );
			}

			$data[] = $property;
		}

		return array(
			'data' => $data,
			'meta' => array( 'next_page' => null === $nextPage ? null : (int) $nextPage ),
		);
	}

	/**
	 * Permit the Docker mock hostname only when WordPress explicitly runs locally.
	 */
	private function isDockerMockUrl( string $url ): bool
	{
		$parts = wp_parse_url( $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );

		return 'mock-api' === $host && 'http' === ( $parts['scheme'] ?? '' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	}

	/**
	 * @param mixed $value Candidate numeric value.
	 */
	private function isPositiveInteger( mixed $value ): bool
	{
		return is_int( $value ) && $value > 0;
	}

	/**
	 * @param mixed $value Candidate numeric value.
	 */
	private function isNonNegativeInteger( mixed $value ): bool
	{
		return is_int( $value ) && $value >= 0;
	}
}
