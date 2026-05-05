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
use function get_option;
use function get_permalink;
use function get_post_meta;
use function get_post_status;
use function get_post_type;
use function home_url;
use function implode;
use function is_wp_error;
use function update_option;
use function update_post_meta;
use function wp_insert_post;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Remediation_Screenshot_Meta_Service
 *
 * Stores and retrieves screenshot URLs for converted Gutenberg pages.
 * Screenshot data is keyed to the converted (target) page ID.
 *
 * Meta keys used:
 *   _etg_ai_elementor_screenshot_url         – Elementor source page screenshot URL (desktop)
 *   _etg_ai_gutenberg_screenshot_url         – Converted Gutenberg page screenshot URL (desktop)
 *   _etg_ai_elementor_screenshot_mobile_url  – Elementor source page screenshot URL (mobile)
 *   _etg_ai_gutenberg_screenshot_mobile_url  – Converted Gutenberg page screenshot URL (mobile)
 *   _etg_ai_screenshot_status                – Generation status constant
 *   _etg_ai_screenshot_generated_at          – Timestamp of last generation attempt
 */
class AI_Remediation_Screenshot_Meta_Service {

	/**
	 * Meta key for the Elementor source page screenshot URL (desktop).
	 */
	const META_ELEMENTOR_URL = '_etg_ai_elementor_screenshot_url';

	/**
	 * Meta key for the converted Gutenberg page screenshot URL (desktop).
	 */
	const META_GUTENBERG_URL = '_etg_ai_gutenberg_screenshot_url';

	/**
	 * Meta key for the Elementor source page screenshot URL (mobile).
	 */
	const META_ELEMENTOR_MOBILE_URL = '_etg_ai_elementor_screenshot_mobile_url';

	/**
	 * Meta key for the converted Gutenberg page screenshot URL (mobile).
	 */
	const META_GUTENBERG_MOBILE_URL = '_etg_ai_gutenberg_screenshot_mobile_url';

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
	 * Get the stored Elementor desktop screenshot URL for a converted Gutenberg page.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string Empty string when not set.
	 */
	public static function get_elementor_url( int $target_id ): string {
		return (string) get_post_meta( $target_id, self::META_ELEMENTOR_URL, true );
	}

	/**
	 * Get the stored Gutenberg desktop screenshot URL for a converted Gutenberg page.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string Empty string when not set.
	 */
	public static function get_gutenberg_url( int $target_id ): string {
		return (string) get_post_meta( $target_id, self::META_GUTENBERG_URL, true );
	}

	/**
	 * Get the stored Elementor mobile screenshot URL for a converted Gutenberg page.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string Empty string when not set.
	 */
	public static function get_elementor_mobile_url( int $target_id ): string {
		return (string) get_post_meta( $target_id, self::META_ELEMENTOR_MOBILE_URL, true );
	}

	/**
	 * Get the stored Gutenberg mobile screenshot URL for a converted Gutenberg page.
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return string Empty string when not set.
	 */
	public static function get_gutenberg_mobile_url( int $target_id ): string {
		return (string) get_post_meta( $target_id, self::META_GUTENBERG_MOBILE_URL, true );
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
	 * Requires all four URLs (desktop + mobile for each page).
	 *
	 * @param int $target_id Converted Gutenberg page ID.
	 * @return bool
	 */
	public static function has_valid_screenshots( int $target_id ): bool {
		return '' !== self::get_elementor_url( $target_id )
			&& '' !== self::get_gutenberg_url( $target_id )
			&& '' !== self::get_elementor_mobile_url( $target_id )
			&& '' !== self::get_gutenberg_mobile_url( $target_id )
			&& self::STATUS_SUCCESS === self::get_status( $target_id );
	}

	/**
	 * Persist screenshot URLs and status for a converted page.
	 *
	 * @param int    $target_id            Converted Gutenberg page ID.
	 * @param string $elementor_url        Elementor source page desktop screenshot URL.
	 * @param string $gutenberg_url        Converted Gutenberg page desktop screenshot URL.
	 * @param string $elementor_mobile_url Elementor source page mobile screenshot URL.
	 * @param string $gutenberg_mobile_url Converted Gutenberg page mobile screenshot URL.
	 * @param string $status               One of the STATUS_* constants.
	 */
	public static function save(
		int $target_id,
		string $elementor_url,
		string $gutenberg_url,
		string $elementor_mobile_url,
		string $gutenberg_mobile_url,
		string $status
	): void {
		update_post_meta( $target_id, self::META_ELEMENTOR_URL, $elementor_url );
		update_post_meta( $target_id, self::META_GUTENBERG_URL, $gutenberg_url );
		update_post_meta( $target_id, self::META_ELEMENTOR_MOBILE_URL, $elementor_mobile_url );
		update_post_meta( $target_id, self::META_GUTENBERG_MOBILE_URL, $gutenberg_mobile_url );
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

		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			$source_url = home_url( '/' );
			$target_url = self::get_or_create_preview_page_url();
		} else {
			$source_url = (string) get_permalink( $source_id );
			$target_url = (string) get_permalink( $target_id );
		}

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

		$elementor_result        = AI_Remediation_Screenshot_Api_Service::fetch( $source_url, AI_Remediation_Screenshot_Api_Service::DEVICE_DESKTOP );
		$gutenberg_result        = AI_Remediation_Screenshot_Api_Service::fetch( $target_url, AI_Remediation_Screenshot_Api_Service::DEVICE_DESKTOP );
		$elementor_mobile_result = AI_Remediation_Screenshot_Api_Service::fetch( $source_url, AI_Remediation_Screenshot_Api_Service::DEVICE_MOBILE );
		$gutenberg_mobile_result = AI_Remediation_Screenshot_Api_Service::fetch( $target_url, AI_Remediation_Screenshot_Api_Service::DEVICE_MOBILE );

		$elementor_url        = $elementor_result['success'] ? $elementor_result['file_url'] : '';
		$gutenberg_url        = $gutenberg_result['success'] ? $gutenberg_result['file_url'] : '';
		$elementor_mobile_url = $elementor_mobile_result['success'] ? $elementor_mobile_result['file_url'] : '';
		$gutenberg_mobile_url = $gutenberg_mobile_result['success'] ? $gutenberg_mobile_result['file_url'] : '';

		$all_ok = $elementor_result['success']
			&& $gutenberg_result['success']
			&& $elementor_mobile_result['success']
			&& $gutenberg_mobile_result['success'];
		$status = $all_ok ? self::STATUS_SUCCESS : self::STATUS_FAILED;

		self::save( $target_id, $elementor_url, $gutenberg_url, $elementor_mobile_url, $gutenberg_mobile_url, $status );

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
	 * Get or create the dedicated preview page used for header/footer screenshots.
	 *
	 * The page is created once, its ID stored in the _etg_hf_preview_page_id option,
	 * and reused on every subsequent call. If the stored page is missing or no longer
	 * published a new one is created to replace it.
	 *
	 * @return string Public permalink of the preview page, or home_url('/') on failure.
	 */
	private static function get_or_create_preview_page_url(): string {
		$option_key = '_etg_hf_preview_page_id';
		$page_id    = (int) get_option( $option_key, 0 );

		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			return (string) get_permalink( $page_id );
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => 'ETG Header & Footer Preview',
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
