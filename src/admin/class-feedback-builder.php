<?php
// phpcs:ignoreFile

/**
 * Assembles the feedback manifest from the job transient and available post meta.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the full feedback manifest payload for a given conversion job.
 */
class Feedback_Builder {

	const CONSENT_VERSION = '2.0';

	const CONSENT_TEXT = 'I consent to sending this conversion report to the plugin developer for quality improvement. It includes my site domain and a hashed site URL, the plugin, WordPress and PHP versions, the active theme and locale, my browser and screen details, the conversion run data, my rating and notes, and the original Elementor data and converted content of each page I select. Page content is sent as-is, is not anonymised, and may contain personal data.';

	/**
	 * Assemble the manifest.
	 *
	 * @param string              $job_id              Job transient ID (without the prefix).
	 * @param int[]               $selected_source_ids Source post IDs to include as items.
	 * @param array<string,mixed> $user_feedback       Rating, issue_type, user_note, etc.
	 * @param array<string,mixed> $client_info         Screen/UA data collected in JS.
	 *
	 * @return array<string,mixed>|null Null when the job is not found.
	 */
	public static function build(
		string $job_id,
		array $selected_source_ids,
		array $user_feedback,
		array $client_info
	): ?array {
		$job = get_transient( 'blockshift_job_' . $job_id );
		if ( empty( $job ) || ! is_array( $job ) ) {
			return null;
		}

		$selected_source_ids = array_values( array_unique( array_filter( array_map( 'intval', $selected_source_ids ) ) ) );
		if ( empty( $selected_source_ids ) ) {
			return null;
		}

		$run_id          = (string) ( $job['run_id'] ?? '' );
		$total_job_items = self::count_job_items( $job );
		$item_count      = count( $selected_source_ids );

		if ( $item_count === 1 ) {
			$scope = 'single_item';
		} elseif ( $item_count >= $total_job_items ) {
			$scope = 'run';
		} else {
			$scope = 'multi_item';
		}

		$feedback_id  = 'fbk_' . gmdate( 'YmdHis' ) . '_' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$submitted_at = gmdate( 'Y-m-d\TH:i:s\Z' );
		$site_hash    = hash( 'sha256', (string) home_url() );

		return array(
			'schema_version'   => '1.0.0',
			'feedback_id'      => $feedback_id,
			'submitted_at'     => $submitted_at,
			'feedback_scope'   => $scope,

			'site'             => self::build_site(),

			'client'           => array(
				'user_agent'         => sanitize_text_field( (string) ( $client_info['user_agent'] ?? '' ) ),
				'screen_width'       => (int) ( $client_info['screen_width'] ?? 0 ),
				'screen_height'      => (int) ( $client_info['screen_height'] ?? 0 ),
				'viewport_width'     => (int) ( $client_info['viewport_width'] ?? 0 ),
				'viewport_height'    => (int) ( $client_info['viewport_height'] ?? 0 ),
				'device_pixel_ratio' => (float) ( $client_info['device_pixel_ratio'] ?? 1.0 ),
			),

			'run'              => self::build_run( $job ),

			'run_summary'      => self::build_run_summary( $job ),

			'user_feedback'    => array(
				'rating'          => isset( $user_feedback['rating'] ) && '' !== $user_feedback['rating'] ? (int) $user_feedback['rating'] : null,
				'issue_type'      => sanitize_key( (string) ( $user_feedback['issue_type'] ?? '' ) ),
				'issue_detail'    => wp_strip_all_tags( substr( (string) ( $user_feedback['issue_detail'] ?? '' ), 0, 500 ) ),
				'user_note'       => wp_strip_all_tags( substr( (string) ( $user_feedback['user_note'] ?? '' ), 0, 2000 ) ),
				'consent_given'   => true,
				'consent_version' => self::CONSENT_VERSION,
				'consent_text'    => self::CONSENT_TEXT,
			),

			'artifact_storage' => array(
				'root_key'     => substr( $site_hash, 0, 20 ) . '/' . $run_id . '/',
				'manifest_key' => substr( $site_hash, 0, 20 ) . '/' . $run_id . '/manifest.json',
			),

			'items'            => self::build_items( $job, $selected_source_ids, $run_id, $user_feedback ),
		);
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Count total items across pages + templates in a job.
	 */
	private static function count_job_items( array $job ): int {
		$pages   = is_array( $job['pages'] ?? null ) ? count( $job['pages'] ) : 0;
		$headers = is_array( $job['templates']['headers'] ?? null ) ? count( $job['templates']['headers'] ) : 0;
		$footers = is_array( $job['templates']['footers'] ?? null ) ? count( $job['templates']['footers'] ) : 0;

		return $pages + $headers + $footers;
	}

	/**
	 * Build the site block (shared environment data).
	 *
	 * @return array<string,mixed>
	 */
	private static function build_site(): array {
		$theme = wp_get_theme();

		return array(
			'site_url_hash'              => hash( 'sha256', (string) home_url() ),
			'site_domain'                => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'plugin_version'             => BLOCKSHIFT_VERSION,
			'wordpress_version'          => get_bloginfo( 'version' ),
			'php_version'                => PHP_VERSION,
			'active_theme'               => (string) $theme->get( 'Name' ),
			'active_theme_is_block_theme' => wp_is_block_theme(),
			'is_multisite'               => is_multisite(),
			'locale'                     => get_locale(),
		);
	}

	/**
	 * Build the run block from job transient data.
	 *
	 * @param array<string,mixed> $job Job transient array.
	 *
	 * @return array<string,mixed>
	 */
	private static function build_run( array $job ): array {
		$started_at   = ! empty( $job['started_at'] ) ? gmdate( 'Y-m-d\TH:i:s\Z', (int) $job['started_at'] ) : null;
		$completed_at = ! empty( $job['completed_at'] ) ? gmdate( 'Y-m-d\TH:i:s\Z', (int) $job['completed_at'] ) : null;
		$duration     = ( ! empty( $job['completed_at'] ) && ! empty( $job['started_at'] ) )
			? ( (int) $job['completed_at'] - (int) $job['started_at'] )
			: null;

		return array(
			'run_id'           => (string) ( $job['run_id'] ?? '' ),
			'conversion_mode'  => sanitize_key( (string) ( $job['mode'] ?? '' ) ),
			'conflict_policy'  => sanitize_key( (string) ( $job['options']['conflict_policy'] ?? '' ) ),
			'started_at'       => $started_at,
			'completed_at'     => $completed_at,
			'duration_seconds' => $duration,
		);
	}

	/**
	 * Build the run_summary block.
	 *
	 * @param array<string,mixed> $job Job transient array.
	 *
	 * @return array<string,mixed>
	 */
	private static function build_run_summary( array $job ): array {
		$js     = is_array( $job['jsonl_stats'] ?? null ) ? $job['jsonl_stats'] : array();
		$counts = is_array( $job['counts'] ?? null ) ? $job['counts'] : array();

		return array(
			'total_items'                 => self::count_job_items( $job ),
			'success_count'               => (int) ( $js['success_count'] ?? $counts['success'] ?? 0 ),
			'success_with_warnings_count' => (int) ( $js['success_with_warnings_count'] ?? 0 ),
			'partial_count'               => (int) ( $js['partial_count'] ?? $counts['partial'] ?? 0 ),
			'failed_count'                => (int) ( $js['failed_count'] ?? $counts['error'] ?? 0 ),
			'skipped_count'               => (int) ( $js['skipped_count'] ?? $counts['skipped'] ?? 0 ),
			'widgets_total'               => (int) ( $js['widgets_total'] ?? 0 ),
			'widgets_converted'           => (int) ( $js['widgets_converted'] ?? 0 ),
			'widgets_failed'              => (int) ( $js['widgets_failed'] ?? 0 ),
			'blocks_generated'            => (int) ( $js['blocks_generated'] ?? 0 ),
			'validation_warnings_count'   => (int) ( $js['validation_warnings_count'] ?? 0 ),
			'unsupported_widgets_summary' => is_array( $js['unsupported_widgets_summary'] ?? null ) ? $js['unsupported_widgets_summary'] : array(),
			'fallback_widgets_summary'    => is_array( $js['fallback_widgets_summary'] ?? null ) ? $js['fallback_widgets_summary'] : array(),
		);
	}

	/**
	 * Build the items array.
	 *
	 * @param array<string,mixed> $job                 Job transient array.
	 * @param int[]               $selected_source_ids Filtered source IDs.
	 * @param string              $run_id              Run identifier.
	 * @param array<string,mixed> $user_feedback       User feedback fields.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_items( array $job, array $selected_source_ids, string $run_id, array $user_feedback ): array {
		$results = is_array( $job['results'] ?? null ) ? $job['results'] : array();
		$items   = array();

		foreach ( $results as $result ) {
			$source_id = (int) ( $result['id'] ?? 0 );
			if ( ! in_array( $source_id, $selected_source_ids, true ) ) {
				continue;
			}

			$target_id  = (int) ( $result['converted_post_id'] ?? $result['target'] ?? 0 );
			$raw_status = (string) ( $result['status'] ?? 'skipped' );
			$widget_log = is_array( $result['widget_log'] ?? null ) ? $result['widget_log'] : null;
			$wl_stats   = is_array( $widget_log['stats'] ?? null ) ? $widget_log['stats'] : array();
			$unsp_types = is_array( $widget_log['unsupported_by_type'] ?? null ) ? $widget_log['unsupported_by_type'] : array();

			$slug = sanitize_title( (string) ( $result['title'] ?? 'item-' . $source_id ) );

			$jsonl_status = Diagnostic_Logger::derive_status( $raw_status, $widget_log );

			// Screenshots from post meta — all four types are stored on the target post.
			$src_urls        = $target_id > 0 ? self::get_screenshot_urls( $target_id, '_blockshift_ai_elementor_screenshot_url' ) : array();
			$src_mob_urls    = $target_id > 0 ? self::get_screenshot_urls( $target_id, '_blockshift_ai_elementor_screenshot_mobile_url' ) : array();
			$gb_urls         = $target_id > 0 ? self::get_screenshot_urls( $target_id, '_blockshift_ai_gutenberg_screenshot_url' ) : array();
			$gb_mob_urls     = $target_id > 0 ? self::get_screenshot_urls( $target_id, '_blockshift_ai_gutenberg_screenshot_mobile_url' ) : array();

			// Elementor JSON.
			$elementor_json = self::get_elementor_json( $source_id );

			// Gutenberg markup.
			$gutenberg_markup = self::get_gutenberg_markup( $target_id );

			// Issue codes from JSONL.
			$item_log_entries = JSONL_Item_Extractor::get_entries( $run_id, $source_id );
			$issue_codes      = array();
			foreach ( $item_log_entries as $entry ) {
				if ( 'item_result' === ( $entry['event'] ?? '' ) && is_array( $entry['issue_codes'] ?? null ) ) {
					$issue_codes = $entry['issue_codes'];
					break;
				}
			}

			// Failure/warning results.
			$failure_results = null;
			if ( in_array( $raw_status, array( 'error', 'partial' ), true )
				|| (int) ( $wl_stats['unsupported'] ?? 0 ) > 0
				|| (int) ( $wl_stats['empty_output'] ?? 0 ) > 0
			) {
				$failure_results = $result;
			}

			// Per-item user feedback.
			$item_ratings = is_array( $user_feedback['item_ratings'] ?? null ) ? $user_feedback['item_ratings'] : array();
			$item_notes   = is_array( $user_feedback['item_notes'] ?? null ) ? $user_feedback['item_notes'] : array();
			$item_rating  = isset( $item_ratings[ $source_id ] ) && '' !== $item_ratings[ $source_id ] ? (int) $item_ratings[ $source_id ] : null;
			$item_note    = isset( $item_notes[ $source_id ] ) ? wp_strip_all_tags( substr( (string) $item_notes[ $source_id ], 0, 1000 ) ) : null;

			$items[] = array(
				'item_feedback_id'          => 'item_' . $source_id . '_' . $slug,
				'source_id'                 => $source_id,
				'target_id'                 => $target_id > 0 ? $target_id : null,
				'title'                     => sanitize_text_field( (string) ( $result['title'] ?? '' ) ),
				'slug'                      => $slug,
				'post_type'                 => sanitize_key( (string) ( $result['post_type'] ?? $result['type'] ?? 'page' ) ),
				'post_type_label'           => sanitize_text_field( (string) ( $result['post_type_label'] ?? '' ) ),
				'template_type'             => isset( $result['role'] ) ? sanitize_key( (string) $result['role'] ) : null,
				'conversion_type'           => sanitize_key( (string) ( $result['type'] ?? 'page' ) ),
				'status'                    => $jsonl_status,
				'duration_seconds'          => round( (float) ( $result['duration'] ?? 0 ), 4 ),
				'widgets_total'             => (int) ( $wl_stats['total'] ?? 0 ),
				'widgets_converted'         => (int) ( $wl_stats['converted'] ?? 0 ),
				'widgets_failed'            => (int) ( $wl_stats['unsupported'] ?? 0 ),
				'blocks_generated'          => null,
				'validation_warnings_count' => 0,
				'unsupported_widgets'       => array_keys( $unsp_types ),
				'fallback_widgets'          => array(),
				'issue_codes'               => $issue_codes,
				'item_rating'               => $item_rating,
				'item_note'                 => $item_note,

				'artifacts'                 => array(
					'source_screenshot_url'          => $src_urls[0] ?? null,
					'source_screenshot_mobile_url'   => $src_mob_urls[0] ?? null,
					'gutenberg_screenshot_url'       => $gb_urls[0] ?? null,
					'gutenberg_screenshot_mobile_url' => $gb_mob_urls[0] ?? null,
				),

				'elementor_json'   => $elementor_json,
				'gutenberg_markup' => $gutenberg_markup,
				'widget_log'       => $widget_log,
				'item_log_entries' => $item_log_entries,
				'failure_results'  => $failure_results,
			);
		}

		return $items;
	}

	/**
	 * Read screenshot URLs from post meta (JSON-encoded array).
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 *
	 * @return string[]
	 */
	private static function get_screenshot_urls( int $post_id, string $meta_key ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		$raw     = get_post_meta( $post_id, $meta_key, true );
		$decoded = json_decode( (string) $raw, true );

		return is_array( $decoded ) ? array_values( array_filter( $decoded ) ) : array();
	}

	/**
	 * Retrieve the Elementor JSON for a source post.
	 *
	 * @param int $source_id Source post ID.
	 *
	 * @return string|null
	 */
	private static function get_elementor_json( int $source_id ): ?string {
		if ( $source_id <= 0 ) {
			return null;
		}

		$raw = get_post_meta( $source_id, '_elementor_data', true );
		return ! empty( $raw ) ? (string) $raw : null;
	}

	/**
	 * Retrieve the converted Gutenberg markup for a target post.
	 *
	 * @param int $target_id Target post ID.
	 *
	 * @return string|null
	 */
	private static function get_gutenberg_markup( int $target_id ): ?string {
		if ( $target_id <= 0 ) {
			return null;
		}

		$post = get_post( $target_id );
		return ( $post instanceof \WP_Post && '' !== $post->post_content ) ? $post->post_content : null;
	}
}
