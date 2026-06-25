<?php
/**
 * External screenshot API service.
 *
 * Responsible for calling the configured external screenshot service,
 * validating the response, and returning a structured result.
 *
 * @package Progressus\MigrateElementorToGutenberg
 */

namespace Progressus\MigrateElementorToGutenberg\Admin\Helper;

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
 * and returns an array of file URLs (one per vertical chunk when the page
 * height exceeds the service's 7500 px chunk limit).
 */
class AI_Remediation_Screenshot_Api_Service {

	/**
	 * WordPress option key for screenshot settings.
	 */
	const SETTINGS_OPTION_KEY = 'metg_screenshot_settings';

	/**
	 * Default request timeout in seconds.
	 */
	const DEFAULT_TIMEOUT = 15;

	/**
	 * Hardcoded screenshot service endpoint URL.
	 */
	const ENDPOINT_URL = 'https://webshot.lvendr.com';

	/**
	 * Hardcoded request timeout in seconds.
	 */
	const HARDCODED_TIMEOUT = 60;

	/**
	 * Device value for desktop screenshots.
	 */
	const DEVICE_DESKTOP = 'desktop';

	/**
	 * Device value for mobile screenshots.
	 */
	const DEVICE_MOBILE = 'mobile';

	/**
	 * Fetch screenshot(s) for the given public page URL.
	 *
	 * Sends the page URL plus a device flag to the configured screenshot service.
	 * When the rendered page height exceeds the service chunk limit the response
	 * contains multiple files; this method returns all of their URLs.
	 *
	 * @param string $page_url The public URL to screenshot.
	 * @param string $device   Either 'desktop' or 'mobile'. Defaults to desktop.
	 * @return array{success: bool, file_urls: string[], count: int, error: string}
	 */
	public static function fetch( string $page_url, string $device = self::DEVICE_DESKTOP ): array {
		$failure = array(
			'success'   => false,
			'file_urls' => array(),
			'count'     => 0,
			'error'     => '',
		);

		$endpoint = self::get_endpoint_url();
		if ( '' === $endpoint ) {
			$failure['error'] = __( 'Screenshot service URL is not configured.', 'blockshift-migrate-from-elementor' );
			return $failure;
		}

		$page_url = trim( $page_url );
		if ( '' === $page_url || false === filter_var( $page_url, FILTER_VALIDATE_URL ) ) {
			$failure['error'] = __( 'An invalid page URL was provided for the screenshot service.', 'blockshift-migrate-from-elementor' );
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
			$failure['error'] = sprintf(
				/* translators: %d: HTTP status code returned by the remote service */
				__( 'Screenshot service returned HTTP status %d.', 'blockshift-migrate-from-elementor' ),
				$http_code
			);
			return $failure;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$failure['error'] = __( 'Screenshot service returned invalid JSON.', 'blockshift-migrate-from-elementor' );
			return $failure;
		}

		if ( empty( $data['success'] ) ) {
			$failure['error'] = __( 'Screenshot service reported a failure in the response.', 'blockshift-migrate-from-elementor' );
			return $failure;
		}

		// New chunked response: expects a "files" array.
		if ( empty( $data['files'] ) || ! is_array( $data['files'] ) ) {
			$failure['error'] = __( 'Screenshot service returned no files in the response.', 'blockshift-migrate-from-elementor' );
			return $failure;
		}

		$file_urls = array();
		foreach ( $data['files'] as $file ) {
			$url = isset( $file['file_url'] ) ? (string) $file['file_url'] : '';
			if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$failure['error'] = __( 'Screenshot service returned a missing or invalid file_url in one of the chunks.', 'blockshift-migrate-from-elementor' );
				return $failure;
			}
			$file_urls[] = esc_url_raw( $url );
		}

		return array(
			'success'   => true,
			'file_urls' => $file_urls,
			'count'     => count( $file_urls ),
			'error'     => '',
		);
	}

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
