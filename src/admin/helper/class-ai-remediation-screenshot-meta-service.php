<?php
/**
 * Post meta service for screenshot URL storage and orchestration.
 *
 * Manages reading, writing, and caching of screenshot URL arrays in post meta
 * on the converted Gutenberg page. Each screenshot type stores a JSON-encoded
 * array of chunk URLs (one element for single-screen pages, multiple for pages
 * that exceed the service's 7500 px chunk limit).
 *
 * @package Progressus\MigrateElementorToGutenberg
 */

namespace Progressus\MigrateElementorToGutenberg\Admin\Helper;

use function current_time;
use function get_option;
use function get_permalink;
use function get_post_meta;
use function get_post_status;
use function get_post_type;
use function home_url;
use function implode;
use function is_array;
use function is_string;
use function is_wp_error;
use function json_decode;
use function update_option;
use function update_post_meta;
use function wp_insert_post;
use function wp_json_encode;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Remediation_Screenshot_Meta_Service
 *
 * Stores and retrieves screenshot chunk-URL arrays for converted Gutenberg pages.
 * Screenshot data is keyed to the converted (target) page ID.
 *
 * Meta keys (each stores a JSON-encoded string[]):
 *   _metg_ai_elementor_screenshot_url         – Elementor source page (desktop)
 *   _metg_ai_gutenberg_screenshot_url         – Converted Gutenberg page (desktop)
 *   _metg_ai_elementor_screenshot_mobile_url  – Elementor source page (mobile)
 *   _metg_ai_gutenberg_screenshot_mobile_url  – Converted Gutenberg page (mobile)
 *   _metg_ai_screenshot_status                – Generation status constant
 *   _metg_ai_screenshot_generated_at          – Timestamp of last generation attempt
 */
class AI_Remediation_Screenshot_Meta_Service {

	const META_ELEMENTOR_URL        = '_metg_ai_elementor_screenshot_url';
	const META_GUTENBERG_URL        = '_metg_ai_gutenberg_screenshot_url';
	const META_ELEMENTOR_MOBILE_URL = '_metg_ai_elementor_screenshot_mobile_url';
	const META_GUTENBERG_MOBILE_URL = '_metg_ai_gutenberg_screenshot_mobile_url';
	const META_STATUS               = '_metg_ai_screenshot_status';
	const META_GENERATED_AT         = '_metg_ai_screenshot_generated_at';

	const STATUS_SUCCESS       = 'success';
	const STATUS_FAILED        = 'failed';
	const STATUS_PENDING       = 'pending';
	const STATUS_NOT_GENERATED = 'not_generated';

	/**
	 * Get stored Elementor desktop screenshot URLs (may be multiple chunks).
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string[] Empty array when not set.
	 */
	public static function get_elementor_urls( int $target_id ): array {
		return self::decode_urls( get_post_meta( $target_id, self::META_ELEMENTOR_URL, true ) );
	}

	/**
	 * Get stored Gutenberg desktop screenshot URLs (may be multiple chunks).
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string[] Empty array when not set.
	 */
	public static function get_gutenberg_urls( int $target_id ): array {
		return self::decode_urls( get_post_meta( $target_id, self::META_GUTENBERG_URL, true ) );
	}

	/**
	 * Get stored Elementor mobile screenshot URLs (may be multiple chunks).
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string[] Empty array when not set.
	 */
	public static function get_elementor_mobile_urls( int $target_id ): array {
		return self::decode_urls( get_post_meta( $target_id, self::META_ELEMENTOR_MOBILE_URL, true ) );
	}

	/**
	 * Get stored Gutenberg mobile screenshot URLs (may be multiple chunks).
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string[] Empty array when not set.
	 */
	public static function get_gutenberg_mobile_urls( int $target_id ): array {
		return self::decode_urls( get_post_meta( $target_id, self::META_GUTENBERG_MOBILE_URL, true ) );
	}

	/**
	 * Get the stored screenshot generation status.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string One of the STATUS_* constants.
	 */
	public static function get_status( int $target_id ): string {
		$status = (string) get_post_meta( $target_id, self::META_STATUS, true );
		return '' === $status ? self::STATUS_NOT_GENERATED : $status;
	}

	/**
	 * Check whether all four screenshot sets are cached and successful.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return bool
	 */
	public static function has_valid_screenshots( int $target_id ): bool {
		return ! empty( self::get_elementor_urls( $target_id ) )
			&& ! empty( self::get_gutenberg_urls( $target_id ) )
			&& ! empty( self::get_elementor_mobile_urls( $target_id ) )
			&& ! empty( self::get_gutenberg_mobile_urls( $target_id ) )
			&& self::STATUS_SUCCESS === self::get_status( $target_id );
	}

	/**
	 * Persist screenshot URL arrays and status for a converted page.
	 *
	 * Each parameter is an array of chunk URLs for that screenshot type.
	 *
	 * @param int      $target_id            Converted Gutenberg page ID.
	 * @param string[] $elementor_urls        Elementor desktop chunk URLs.
	 * @param string[] $gutenberg_urls        Gutenberg desktop chunk URLs.
	 * @param string[] $elementor_mobile_urls Elementor mobile chunk URLs.
	 * @param string[] $gutenberg_mobile_urls Gutenberg mobile chunk URLs.
	 * @param string   $status               One of the STATUS_* constants.
	 */
	public static function save(
		int $target_id,
		array $elementor_urls,
		array $gutenberg_urls,
		array $elementor_mobile_urls,
		array $gutenberg_mobile_urls,
		string $status
	): void {
		update_post_meta( $target_id, self::META_ELEMENTOR_URL, wp_json_encode( $elementor_urls ) );
		update_post_meta( $target_id, self::META_GUTENBERG_URL, wp_json_encode( $gutenberg_urls ) );
		update_post_meta( $target_id, self::META_ELEMENTOR_MOBILE_URL, wp_json_encode( $elementor_mobile_urls ) );
		update_post_meta( $target_id, self::META_GUTENBERG_MOBILE_URL, wp_json_encode( $gutenberg_mobile_urls ) );
		update_post_meta( $target_id, self::META_STATUS, $status );
		update_post_meta( $target_id, self::META_GENERATED_AT, current_time( 'mysql' ) );
	}

	/**
	 * Update only the status and timestamp fields without touching the URL arrays.
	 *
	 * @param int    $target_id Converted Gutenberg page ID.
	 * @param string $status    One of the STATUS_* constants.
	 */
	public static function save_status( int $target_id, string $status ): void {
		update_post_meta( $target_id, self::META_STATUS, $status );
		update_post_meta( $target_id, self::META_GENERATED_AT, current_time( 'mysql' ) );
	}

	/**
	 * Generate screenshots for a source/target page pair and store the results.
	 *
	 * @param int  $source_id Elementor source page ID.
	 * @param int  $target_id Converted Gutenberg page ID.
	 * @param bool $force     Force regeneration even when cached URLs exist.
	 * @return array{success: bool, error: string}
	 */
	public static function generate_and_store( int $source_id, int $target_id, bool $force = false ): array {
		if ( ! $force && self::has_valid_screenshots( $target_id ) ) {
			return array(
				'success' => true,
				'error'   => '',
			);
		}

		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			$source_url = home_url( '/' );
			$target_url = self::get_or_create_preview_page_url();
		} else {
			$source_url = (string) get_permalink( $source_id );
			$target_url = (string) get_permalink( $target_id );
		}

		if ( '' === $source_url || '' === $target_url ) {
			self::save_status( $target_id, self::STATUS_FAILED );
			return array(
				'success' => false,
				'error'   => __( 'Could not resolve public URLs for the source or target page.', 'migrate-elementor-to-gutenberg' ),
			);
		}

		self::save_status( $target_id, self::STATUS_PENDING );

		$elementor_result        = AI_Remediation_Screenshot_Api_Service::fetch( $source_url, AI_Remediation_Screenshot_Api_Service::DEVICE_DESKTOP );
		$gutenberg_result        = AI_Remediation_Screenshot_Api_Service::fetch( $target_url, AI_Remediation_Screenshot_Api_Service::DEVICE_DESKTOP );
		$elementor_mobile_result = AI_Remediation_Screenshot_Api_Service::fetch( $source_url, AI_Remediation_Screenshot_Api_Service::DEVICE_MOBILE );
		$gutenberg_mobile_result = AI_Remediation_Screenshot_Api_Service::fetch( $target_url, AI_Remediation_Screenshot_Api_Service::DEVICE_MOBILE );

		$elementor_urls        = $elementor_result['success'] ? $elementor_result['file_urls'] : array();
		$gutenberg_urls        = $gutenberg_result['success'] ? $gutenberg_result['file_urls'] : array();
		$elementor_mobile_urls = $elementor_mobile_result['success'] ? $elementor_mobile_result['file_urls'] : array();
		$gutenberg_mobile_urls = $gutenberg_mobile_result['success'] ? $gutenberg_mobile_result['file_urls'] : array();

		$all_ok = $elementor_result['success']
			&& $gutenberg_result['success']
			&& $elementor_mobile_result['success']
			&& $gutenberg_mobile_result['success'];

		self::save(
			$target_id,
			$elementor_urls,
			$gutenberg_urls,
			$elementor_mobile_urls,
			$gutenberg_mobile_urls,
			$all_ok ? self::STATUS_SUCCESS : self::STATUS_FAILED
		);

		if ( ! $all_ok ) {
			$errors = array();
			if ( ! $elementor_result['success'] ) {
				$errors[] = 'Elementor (desktop): ' . $elementor_result['error'];
			}
			if ( ! $gutenberg_result['success'] ) {
				$errors[] = 'Gutenberg (desktop): ' . $gutenberg_result['error'];
			}
			if ( ! $elementor_mobile_result['success'] ) {
				$errors[] = 'Elementor (mobile): ' . $elementor_mobile_result['error'];
			}
			if ( ! $gutenberg_mobile_result['success'] ) {
				$errors[] = 'Gutenberg (mobile): ' . $gutenberg_mobile_result['error'];
			}
			return array(
				'success' => false,
				'error'   => implode( ' | ', $errors ),
			);
		}

		return array(
			'success' => true,
			'error'   => '',
		);
	}

	/**
	 * Decode a stored meta value into an array of URL strings.
	 *
	 * Handles three cases:
	 *  - JSON-encoded array (current format): decoded and returned.
	 *  - Plain URL string (legacy single-URL format): wrapped in a one-element array.
	 *  - Empty / invalid: returns an empty array.
	 *
	 * @param mixed $raw Raw value from get_post_meta().
	 * @return string[]
	 */
	private static function decode_urls( $raw ): array {
		if ( is_array( $raw ) ) {
			return array_values( array_filter( $raw, 'is_string' ) );
		}

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			return array_values( array_filter( $decoded, 'is_string' ) );
		}

		// Legacy: plain URL string stored before chunked format was introduced.
		return array( $raw );
	}

	/**
	 * Get or create the dedicated preview page used for header/footer screenshots.
	 *
	 * @return string Public permalink of the preview page, or home_url('/') on failure.
	 */
	private static function get_or_create_preview_page_url(): string {
		$option_key = '_metg_hf_preview_page_id';
		$page_id    = (int) get_option( $option_key, 0 );

		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			return (string) get_permalink( $page_id );
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => 'Header & Footer Preview',
				'post_content' => '<!-- wp:paragraph --><p>This page is used by the Elementor to Gutenberg plugin to preview header and footer templates.</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( is_wp_error( $new_id ) || $new_id <= 0 ) {
			return home_url( '/' );
		}

		update_option( $option_key, $new_id );

		return (string) get_permalink( $new_id );
	}
}
