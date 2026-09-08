<?php
/**
 * Settings storage and validation.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Admin;

final class Settings {

	public const OPTION_NAME = 'property_sync_settings';

	public const PAGE_SLUG = 'property-sync';

	private const SETTINGS_GROUP = 'property_sync_settings_group';

	private const INTERVALS = array( 'disabled', 'hourly', 'twicedaily', 'daily' );

	/**
	 * Register settings hooks.
	 */
	public function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register the structured option and its fields.
	 */
	public function register(): void {
		$this->ensureOptionExists();

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
			)
		);

		add_settings_section(
			'property_sync_connection',
			__( 'Connection', 'property-sync' ),
			array( $this, 'renderSectionDescription' ),
			self::PAGE_SLUG
		);

		add_settings_field( 'property_sync_api_url', __( 'API URL', 'property-sync' ), array( $this, 'renderApiUrlField' ), self::PAGE_SLUG, 'property_sync_connection' );
		add_settings_field( 'property_sync_api_token', __( 'API token', 'property-sync' ), array( $this, 'renderApiTokenField' ), self::PAGE_SLUG, 'property_sync_connection' );
		add_settings_field( 'property_sync_interval', __( 'Sync interval', 'property-sync' ), array( $this, 'renderIntervalField' ), self::PAGE_SLUG, 'property_sync_connection' );
	}

	/**
	 * Get the saved settings with defaults applied.
	 *
	 * @return array{api_url: string, api_token: string, interval: string}
	 */
	public function get(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), $this->defaults() );
	}

	/**
	 * Get the effective API token, preferring the deployment constant.
	 */
	public function getApiToken(): string {
		if ( $this->isTokenOverridden() ) {
			return (string) constant( 'PROPERTY_SYNC_API_TOKEN' );
		}

		return $this->get()['api_token'];
	}

	/**
	 * Whether a deployment-level token overrides the stored setting.
	 */
	public function isTokenOverridden(): bool {
		return defined( 'PROPERTY_SYNC_API_TOKEN' ) && '' !== trim( (string) constant( 'PROPERTY_SYNC_API_TOKEN' ) );
	}

	/**
	 * Validate submitted settings without replacing a saved token with an empty field.
	 *
	 * @param mixed $input Raw Settings API input.
	 * @return array{api_url: string, api_token: string, interval: string}
	 */
	public function sanitize( mixed $input ): array {
		$current  = $this->get();
		$input    = is_array( $input ) ? $input : array();
		$url      = trim( (string) ( $input['api_url'] ?? '' ) );
		$interval = (string) ( $input['interval'] ?? $current['interval'] );

		if ( '' !== $url && ! $this->isAllowedApiUrl( $url ) ) {
			add_settings_error( self::OPTION_NAME, 'invalid_api_url', __( 'Enter a valid HTTPS API URL. HTTP is allowed only for local development hosts.', 'property-sync' ) );
			$url = $current['api_url'];
		}

		if ( ! in_array( $interval, self::INTERVALS, true ) ) {
			add_settings_error( self::OPTION_NAME, 'invalid_interval', __( 'Choose a valid synchronization interval.', 'property-sync' ) );
			$interval = $current['interval'];
		}

		$token = $current['api_token'];

		if ( ! $this->isTokenOverridden() && isset( $input['api_token'] ) ) {
			$submittedToken = trim( (string) $input['api_token'] );
			if ( '' !== $submittedToken ) {
				$token = sanitize_text_field( $submittedToken );
			}
		}

		return array(
			'api_url'   => esc_url_raw( $url ),
			'api_token' => $token,
			'interval'  => $interval,
		);
	}

	/**
	 * Render the section guidance.
	 */
	public function renderSectionDescription(): void {
		echo '<p>' . esc_html__( 'Configure the external API used by future synchronization runs.', 'property-sync' ) . '</p>';
	}

	/**
	 * Render the API URL field.
	 */
	public function renderApiUrlField(): void {
		$settings = $this->get();
		printf(
			'<input class="regular-text code" id="property_sync_api_url" name="%1$s[api_url]" type="url" value="%2$s" placeholder="https://api.example.com/properties" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['api_url'] )
		);
		echo '<p class="description">' . esc_html__( 'Use HTTPS in production. Local HTTP endpoints are accepted for development.', 'property-sync' ) . '</p>';
	}

	/**
	 * Render a masked token field.
	 */
	public function renderApiTokenField(): void {
		if ( $this->isTokenOverridden() ) {
			echo '<input class="regular-text" type="text" value="' . esc_attr__( 'Defined by PROPERTY_SYNC_API_TOKEN', 'property-sync' ) . '" readonly />';
			echo '<p class="description">' . esc_html__( 'This value is supplied by your deployment configuration and cannot be changed here.', 'property-sync' ) . '</p>';
			return;
		}

		$hasToken = '' !== $this->get()['api_token'];
		printf(
			'<input class="regular-text" id="property_sync_api_token" name="%1$s[api_token]" type="password" value="" autocomplete="new-password" placeholder="%2$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $hasToken ? '********' : __( 'Enter API token', 'property-sync' ) )
		);
		echo '<p class="description">' . esc_html__( 'Leave blank to keep the saved token. The token is never shown in full.', 'property-sync' ) . '</p>';
	}

	/**
	 * Render the scheduling interval selector.
	 */
	public function renderIntervalField(): void {
		$interval = $this->get()['interval'];
		$labels   = array(
			'disabled'   => __( 'Disabled', 'property-sync' ),
			'hourly'     => __( 'Hourly', 'property-sync' ),
			'twicedaily' => __( 'Twice daily', 'property-sync' ),
			'daily'      => __( 'Daily', 'property-sync' ),
		);

		echo '<select id="property_sync_interval" name="' . esc_attr( self::OPTION_NAME ) . '[interval]">';
		foreach ( $labels as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $interval, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Ensure this security-sensitive option is not autoloaded.
	 */
	private function ensureOptionExists(): void {
		if ( null !== get_option( self::OPTION_NAME, null ) ) {
			return;
		}

		add_option( self::OPTION_NAME, $this->defaults(), '', false );
	}

	/**
	 * Validate a URL and allow insecure transport only for development hosts.
	 */
	private function isAllowedApiUrl( string $url ): bool {
		$url    = esc_url_raw( $url );
		$parts  = wp_parse_url( $url );
		$scheme = $parts['scheme'] ?? '';
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );

		if ( '' === $url || '' === $host ) {
			return false;
		}

		if ( 'https' === $scheme ) {
			return true;
		}

		return 'http' === $scheme && in_array( $host, array( 'localhost', '127.0.0.1', '::1', 'mock-api' ), true );
	}

	/**
	 * @return array{api_url: string, api_token: string, interval: string}
	 */
	private function defaults(): array {
		return array(
			'api_url'   => '',
			'api_token' => '',
			'interval'  => 'disabled',
		);
	}
}
