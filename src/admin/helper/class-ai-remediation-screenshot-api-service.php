<?php
/**
 * External screenshot API service.
 *
 * Responsible for calling the configured external screenshot service,
 * validating the response, and returning a structured result.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function esc_url_raw;
use function filter_var;
use function get_option;
use function is_array;
use function is_wp_error;
use function json_decode;
use function sprintf;
use function trim;
use function update_option;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Remediation_Screenshot_Api_Service
 *
 * Calls the external screenshot API endpoint, validates the JSON response,
 * and returns the file_url or a structured failure result.
 */
class AI_Remediation_Screenshot_Api_Service {

	/**
	 * WordPress option key for screenshot settings.
	 */
	const SETTINGS_OPTION_KEY = 'etg_screenshot_settings';

	/**
	 * Default request timeout in seconds.
	 */
	const DEFAULT_TIMEOUT = 15;

	/**
	 * Device value for desktop screenshots.
	 */
	const DEVICE_DESKTOP = 'desktop';

	/**
	 * Device value for mobile screenshots.
	 */
	const DEVICE_MOBILE = 'mobile';

	/**
	 * Fetch a screenshot for the given public page URL.
	 *
	 * Sends the page URL plus a device flag to the configured screenshot service.
	 * Validates the JSON response and extracts file_url.
	 *
	 * @param string $page_url The public URL to screenshot.
	 * @param string $device   Either 'desktop' or 'mobile'. Defaults to desktop.
	 * @return array{success: bool, file_url: string, error: string}
	 */
	public static function fetch( string $page_url, string $device = self::DEVICE_DESKTOP ): array {
		$failure = array(
			'success'  => false,
			'file_url' => '',
			'error'    => '',
		);

		$endpoint = self::get_endpoint_url();
		if ( '' === $endpoint ) {
			$failure['error'] = __( 'Screenshot service URL is not configured.', 'elementor-to-gutenberg' );
			return $failure;
		}

		$page_url = trim( $page_url );
		if ( '' === $page_url || false === filter_var( $page_url, FILTER_VALIDATE_URL ) ) {
			$failure['error'] = __( 'An invalid page URL was provided for the screenshot service.', 'elementor-to-gutenberg' );
			return $failure;
		}

		$device = ( self::DEVICE_MOBILE === $device ) ? self::DEVICE_MOBILE : self::DEVICE_DESKTOP;

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => self::get_timeout(),
				'sslverify' => true,
				'headers'   => array( 'Content-Type' => 'application/json' ),
				'body'      => wp_json_encode(
					array(
						'url'    => $page_url,
						'device' => $device,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$failure['error'] = $response->get_error_message();
			return $failure;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			/* translators: %d: HTTP status code returned by the remote service */
			$failure['error'] = sprintf(
				__( 'Screenshot service returned HTTP status %d.', 'elementor-to-gutenberg' ),
				$http_code
			);
			return $failure;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$failure['error'] = __( 'Screenshot service returned invalid JSON.', 'elementor-to-gutenberg' );
			return $failure;
		}

		if ( empty( $data['success'] ) ) {
			$failure['error'] = __( 'Screenshot service reported a failure in the response.', 'elementor-to-gutenberg' );
			return $failure;
		}

		$file_url = isset( $data['file_url'] ) ? (string) $data['file_url'] : '';
		if ( '' === $file_url || false === filter_var( $file_url, FILTER_VALIDATE_URL ) ) {
			$failure['error'] = __( 'Screenshot service returned a missing or invalid file_url.', 'elementor-to-gutenberg' );
			return $failure;
		}

		return array(
			'success'  => true,
			'file_url' => esc_url_raw( $file_url ),
			'error'    => '',
		);
	}

	/**
	 * Hardcoded screenshot service endpoint URL.
	 */
	const ENDPOINT_URL = 'http://lvendr.xyz/screanshots';

	/**
	 * Hardcoded request timeout in seconds.
	 */
	const HARDCODED_TIMEOUT = 60;

	/**
	 * Get the screenshot service endpoint URL.
	 *
	 * @return string
	 */
	public static function get_endpoint_url(): string {
		return self::ENDPOINT_URL;
	}

	/**
	 * Get the request timeout in seconds.
	 *
	 * @return int
	 */
	public static function get_timeout(): int {
		return self::HARDCODED_TIMEOUT;
	}

	/**
	 * Automatic screenshot generation after conversion is disabled.
	 *
	 * @return bool
	 */
	public static function is_auto_generate_enabled(): bool {
		return false;
	}

	/**
	 * Load all screenshot settings from the WordPress options table.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$settings = get_option( self::SETTINGS_OPTION_KEY, array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Persist screenshot settings to the WordPress options table.
	 *
	 * @param array $settings Sanitized settings array.
	 */
	public static function save_settings( array $settings ): void {
		update_option( self::SETTINGS_OPTION_KEY, $settings, false );
	}
}
