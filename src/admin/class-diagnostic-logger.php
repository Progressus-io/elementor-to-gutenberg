<?php
// phpcs:ignoreFile

/**
 * Structured JSONL diagnostic logger for Elementor-to-Gutenberg conversions.
 *
 * Writes one JSON object per line to conversion-log.jsonl inside the uploads
 * directory.  Every event includes a run_id so runs can be isolated later.
 *
 * Design rules
 *  - All public methods are static; no instance state is needed.
 *  - write() wraps everything in a try/catch — a log failure must never
 *    interrupt a conversion.
 *  - Sensitive values are redacted before writing.
 *  - Uses wp_json_encode(), wp_mkdir_p(), wp_upload_dir() throughout.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

use function gmdate;
use function hash;
use function md5;
use function uniqid;
use function wp_json_encode;
use function wp_mkdir_p;
use function wp_upload_dir;

defined( 'ABSPATH' ) || exit;

/**
 * JSONL diagnostic logger.
 */
class Diagnostic_Logger {

	// ── Issue code constants ──────────────────────────────────────────────────

	const UNSUPPORTED_WIDGET        = 'UNSUPPORTED_WIDGET';
	const WIDGET_FALLBACK_USED      = 'WIDGET_FALLBACK_USED';
	const BLOCK_VALIDATION_WARNING  = 'BLOCK_VALIDATION_WARNING';
	const BLOCK_VALIDATION_FAILED   = 'BLOCK_VALIDATION_FAILED';
	const CSS_GENERATION_FAILED     = 'CSS_GENERATION_FAILED';
	const MEDIA_NOT_FOUND           = 'MEDIA_NOT_FOUND';
	const IMAGE_DOWNLOAD_FAILED     = 'IMAGE_DOWNLOAD_FAILED';
	const AI_REQUEST_FAILED         = 'AI_REQUEST_FAILED';
	const AI_RESPONSE_INVALID       = 'AI_RESPONSE_INVALID';
	const AI_OUTPUT_SANITIZED       = 'AI_OUTPUT_SANITIZED';
	const EMPTY_ELEMENTOR_DATA      = 'EMPTY_ELEMENTOR_DATA';
	const INVALID_ELEMENTOR_JSON    = 'INVALID_ELEMENTOR_JSON';
	const MISSING_TEMPLATE_LOCATION = 'MISSING_TEMPLATE_LOCATION';
	const UNKNOWN_WIDGET_HANDLER    = 'UNKNOWN_WIDGET_HANDLER';
	const CONVERSION_EXCEPTION      = 'CONVERSION_EXCEPTION';
	const GENERATED_MARKUP_EMPTY    = 'GENERATED_MARKUP_EMPTY';
	const STYLE_MAPPING_SKIPPED     = 'STYLE_MAPPING_SKIPPED';
	const EXTERNAL_CSS_GENERATED    = 'EXTERNAL_CSS_GENERATED';
	const EXTERNAL_CSS_FAILED       = 'EXTERNAL_CSS_FAILED';

	// ── File settings ─────────────────────────────────────────────────────────

	/** Rotate when the file exceeds this size (5 MB). */
	const MAX_FILE_BYTES = 5242880;

	// ── Key-based redaction list ──────────────────────────────────────────────

	/**
	 * Any array key whose lowercased form contains one of these substrings will
	 * have its value replaced with a placeholder.
	 *
	 * @var string[]
	 */
	private const SENSITIVE_KEYS = array(
		'api_key',
		'api_secret',
		'secret_key',
		'secret',
		'password',
		'passwd',
		'pass',
		'token',
		'access_token',
		'refresh_token',
		'auth_token',
		'bearer',
		'authorization',
		'auth',
		'nonce',
		'cookie',
		'cookies',
		'license_key',
		'license',
		'db_password',
		'db_pass',
		'database_password',
	);

	// ── Public API ────────────────────────────────────────────────────────────

	/**
	 * Generate a stable run identifier.
	 * Format: metg_YYYYMMDD_HHMMSS_<6-char hex>
	 */
	public static function generate_run_id(): string {
		return 'metg_' . gmdate( 'Ymd_His' ) . '_' . substr( md5( uniqid( '', true ) ), 0, 6 );
	}

	/**
	 * Absolute path to the JSONL log file.
	 */
	public static function log_path(): string {
		$upload = wp_upload_dir( null, false );
		return $upload['basedir'] . '/metg/conversion-log.jsonl';
	}

	/** Log a run_start event. */
	public static function log_run_start( string $run_id, array $context ): void {
		self::write( array_merge( array( 'event' => 'run_start', 'run_id' => $run_id ), $context ) );
	}

	/** Log an item_start event. */
	public static function log_item_start( string $run_id, array $item ): void {
		self::write( array_merge( array( 'event' => 'item_start', 'run_id' => $run_id ), $item ) );
	}

	/** Log an item_result event. */
	public static function log_item_result( string $run_id, array $data ): void {
		self::write( array_merge( array( 'event' => 'item_result', 'run_id' => $run_id ), $data ) );
	}

	/** Log an issue event (one per widget/problem). */
	public static function log_issue( string $run_id, array $issue ): void {
		self::write( array_merge( array( 'event' => 'issue', 'run_id' => $run_id ), $issue ) );
	}

	/** Log a run_summary event at the end of a batch run. */
	public static function log_run_summary( string $run_id, array $summary ): void {
		self::write( array_merge( array( 'event' => 'run_summary', 'run_id' => $run_id ), $summary ) );
	}

	/** Log a run_end event. */
	public static function log_run_end( string $run_id, array $data ): void {
		self::write( array_merge( array( 'event' => 'run_end', 'run_id' => $run_id ), $data ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Map a widget_log entry to an issue code + severity pair.
	 *
	 * @param  array $entry  One entry from Conversion_Log::get_entries().
	 * @return array{code:string, severity:string}
	 */
	public static function issue_code_for_entry( array $entry ): array {
		switch ( $entry['type'] ?? '' ) {
			case 'unsupported':
				return array( 'code' => self::UNSUPPORTED_WIDGET, 'severity' => 'medium' );
			case 'empty_output':
				return array( 'code' => self::WIDGET_FALLBACK_USED, 'severity' => 'low' );
			default:
				return array( 'code' => self::UNKNOWN_WIDGET_HANDLER, 'severity' => 'medium' );
		}
	}

	/**
	 * Derive the canonical JSONL status from a raw result status + widget log.
	 *
	 * @param  string     $raw_status  'success' | 'error' | 'skipped'
	 * @param  array|null $widget_log  to_array() output from Conversion_Log, or null.
	 */
	public static function derive_status( string $raw_status, ?array $widget_log ): string {
		if ( 'error' === $raw_status ) {
			return 'FAILED';
		}
		if ( 'skipped' === $raw_status ) {
			return 'SKIPPED';
		}
		if ( 'success' === $raw_status ) {
			if ( null === $widget_log ) {
				return 'SUCCESS';
			}
			$stats      = is_array( $widget_log['stats'] ?? null ) ? $widget_log['stats'] : array();
			$has_issues = ( (int) ( $stats['unsupported'] ?? 0 ) > 0 )
			              || ( (int) ( $stats['empty_output'] ?? 0 ) > 0 );
			return $has_issues ? 'SUCCESS_WITH_WARNINGS' : 'SUCCESS';
		}
		return 'PARTIAL';
	}

	/**
	 * Recursively redact sensitive keys from an array before it is logged.
	 *
	 * @param  array $data  Any associative array.
	 * @return array        The same structure with sensitive values replaced.
	 */
	public static function redact( array $data ): array {
		return self::redact_recursive( $data );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * @param  mixed  $value
	 * @param  string $key    The parent key (empty at the top level).
	 * @return mixed
	 */
	private static function redact_recursive( $value, string $key = '' ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::redact_recursive( $v, (string) $k );
			}
			return $out;
		}

		if ( is_string( $value ) && '' !== $key ) {
			$lower = strtolower( $key );
			foreach ( self::SENSITIVE_KEYS as $sensitive ) {
				if ( false !== strpos( $lower, $sensitive ) ) {
					return self::placeholder_for( $sensitive );
				}
			}
		}

		return $value;
	}

	private static function placeholder_for( string $sensitive ): string {
		if ( false !== strpos( $sensitive, 'api_key' )
		     || false !== strpos( $sensitive, 'secret' ) ) {
			return '[redacted_api_key]';
		}
		if ( false !== strpos( $sensitive, 'token' )
		     || false !== strpos( $sensitive, 'bearer' )
		     || false !== strpos( $sensitive, 'auth' ) ) {
			return '[redacted_token]';
		}
		if ( false !== strpos( $sensitive, 'cookie' ) ) {
			return '[redacted_cookie]';
		}
		if ( false !== strpos( $sensitive, 'nonce' ) ) {
			return '[redacted_nonce]';
		}
		if ( false !== strpos( $sensitive, 'password' )
		     || false !== strpos( $sensitive, 'passwd' )
		     || 'pass' === $sensitive ) {
			return '[redacted_password]';
		}
		if ( false !== strpos( $sensitive, 'license' ) ) {
			return '[redacted_license]';
		}
		return '[redacted]';
	}

	/**
	 * Append one event as a JSONL line.  Never throws.
	 *
	 * @param array $event  The event payload (event + run_id already set by callers).
	 */
	private static function write( array $event ): void {
		try {
			if ( ! isset( $event['timestamp'] ) ) {
				$event = array_merge( array( 'timestamp' => gmdate( 'c' ) ), $event );
			}

			$event = self::redact( $event );

			$path = self::log_path();
			$dir  = dirname( $path );

			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			// Rotate the file once it exceeds the size limit.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
			if ( file_exists( $path ) && filesize( $path ) > self::MAX_FILE_BYTES ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $path, $path . '.1' );
			}

			$line = wp_json_encode( $event );
			if ( false === $line ) {
				return;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path, $line . "\n", FILE_APPEND | LOCK_EX );

		} catch ( \Throwable $e ) {
			// Logging must never interrupt a conversion.
		}
	}
}
