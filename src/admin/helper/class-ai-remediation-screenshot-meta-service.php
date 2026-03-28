<?php
/**
 * Post meta service for screenshot URL storage and orchestration.
 *
 * Manages reading, writing, and caching of screenshot URLs in post meta
 * on the converted Gutenberg page. Also orchestrates calls to the API
 * service and stores the results.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function current_time;
use function get_permalink;
use function get_post_meta;
use function implode;
use function update_post_meta;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Remediation_Screenshot_Meta_Service
 *
 * Stores and retrieves screenshot URLs for converted Gutenberg pages.
 * Screenshot data is keyed to the converted (target) page ID.
 *
 * Meta keys used:
 *   _etg_ai_elementor_screenshot_url  – Elementor source page screenshot URL
 *   _etg_ai_gutenberg_screenshot_url  – Converted Gutenberg page screenshot URL
 *   _etg_ai_screenshot_status         – Generation status constant
 *   _etg_ai_screenshot_generated_at   – Timestamp of last generation attempt
 */
class AI_Remediation_Screenshot_Meta_Service {

	/**
	 * Meta key for the Elementor source page screenshot URL.
	 */
	const META_ELEMENTOR_URL = '_etg_ai_elementor_screenshot_url';

	/**
	 * Meta key for the converted Gutenberg page screenshot URL.
	 */
	const META_GUTENBERG_URL = '_etg_ai_gutenberg_screenshot_url';

	/**
	 * Meta key for the screenshot generation status.
	 */
	const META_STATUS = '_etg_ai_screenshot_status';

	/**
	 * Meta key for the screenshot generation timestamp.
	 */
	const META_GENERATED_AT = '_etg_ai_screenshot_generated_at';

	/**
	 * Status: both screenshots were generated successfully.
	 */
	const STATUS_SUCCESS = 'success';

	/**
	 * Status: screenshot generation was attempted but failed.
	 */
	const STATUS_FAILED = 'failed';

	/**
	 * Status: generation is in progress.
	 */
	const STATUS_PENDING = 'pending';

	/**
	 * Status: no generation attempt has been made yet.
	 */
	const STATUS_NOT_GENERATED = 'not_generated';

	/**
	 * Get the stored Elementor screenshot URL for a converted Gutenberg page.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string Empty string when not set.
	 */
	public static function get_elementor_url( int $target_id ): string {
		return (string) get_post_meta( $target_id, self::META_ELEMENTOR_URL, true );
	}

	/**
	 * Get the stored Gutenberg screenshot URL for a converted Gutenberg page.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string Empty string when not set.
	 */
	public static function get_gutenberg_url( int $target_id ): string {
		return (string) get_post_meta( $target_id, self::META_GUTENBERG_URL, true );
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
	 * Check whether non-empty, successful screenshot URLs are already cached.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return bool
	 */
	public static function has_valid_screenshots( int $target_id ): bool {
		return '' !== self::get_elementor_url( $target_id )
			&& '' !== self::get_gutenberg_url( $target_id )
			&& self::STATUS_SUCCESS === self::get_status( $target_id );
	}

	/**
	 * Persist screenshot URLs and status for a converted page.
	 *
	 * @param int    $target_id     Converted Gutenberg page ID.
	 * @param string $elementor_url Elementor source page screenshot URL.
	 * @param string $gutenberg_url Converted Gutenberg page screenshot URL.
	 * @param string $status        One of the STATUS_* constants.
	 */
	public static function save(
		int $target_id,
		string $elementor_url,
		string $gutenberg_url,
		string $status
	): void {
		update_post_meta( $target_id, self::META_ELEMENTOR_URL, $elementor_url );
		update_post_meta( $target_id, self::META_GUTENBERG_URL, $gutenberg_url );
		update_post_meta( $target_id, self::META_STATUS, $status );
		update_post_meta( $target_id, self::META_GENERATED_AT, current_time( 'mysql' ) );
	}

	/**
	 * Update only the status and timestamp fields without touching the URLs.
	 *
	 * Used to mark pending or failed state before or after an attempt.
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
	 * Skips API calls when valid URLs are already cached, unless $force is true.
	 * Failures are stored in meta and do not throw exceptions.
	 *
	 * Caching behaviour:
	 *   - On first call after conversion: both URLs are fetched and written to meta.
	 *   - On subsequent calls: has_valid_screenshots() returns true, so the API is
	 *     not called again and the cached URLs are used as-is.
	 *   - When $force is true (Regenerate action): the API is always called and the
	 *     stored URLs are replaced with the fresh values.
	 *
	 * @param int  $source_id Elementor source page ID.
	 * @param int  $target_id Converted Gutenberg page ID.
	 * @param bool $force     Force regeneration even when cached URLs exist.
	 * @return array{success: bool, error: string}
	 */
	public static function generate_and_store( int $source_id, int $target_id, bool $force = false ): array {
		// Return early if valid screenshots are already cached.
		if ( ! $force && self::has_valid_screenshots( $target_id ) ) {
			return array(
				'success' => true,
				'error'   => '',
			);
		}

		$source_url = (string) get_permalink( $source_id );
		$target_url = (string) get_permalink( $target_id );

		if ( '' === $source_url || '' === $target_url ) {
			$error = __( 'Could not resolve public URLs for the source or target page.', 'elementor-to-gutenberg' );
			self::save_status( $target_id, self::STATUS_FAILED );
			return array(
				'success' => false,
				'error'   => $error,
			);
		}

		// Mark as pending before the remote calls.
		self::save_status( $target_id, self::STATUS_PENDING );

		$elementor_result = AI_Remediation_Screenshot_Api_Service::fetch( $source_url );
		$gutenberg_result = AI_Remediation_Screenshot_Api_Service::fetch( $target_url );

		$elementor_url = $elementor_result['success'] ? $elementor_result['file_url'] : '';
		$gutenberg_url = $gutenberg_result['success'] ? $gutenberg_result['file_url'] : '';

		$both_ok = $elementor_result['success'] && $gutenberg_result['success'];
		$status  = $both_ok ? self::STATUS_SUCCESS : self::STATUS_FAILED;

		self::save( $target_id, $elementor_url, $gutenberg_url, $status );

		if ( ! $both_ok ) {
			$errors = array();
			if ( ! $elementor_result['success'] ) {
				$errors[] = 'Elementor: ' . $elementor_result['error'];
			}
			if ( ! $gutenberg_result['success'] ) {
				$errors[] = 'Gutenberg: ' . $gutenberg_result['error'];
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
}
