<?php
// phpcs:ignoreFile

/**
 * Extracts JSONL diagnostic events for a specific item from the conversion log.
 *
 * @package Progressus\MigrateElementorToGutenberg
 */

namespace Progressus\MigrateElementorToGutenberg\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the JSONL log file(s) and returns all events that belong to a given
 * run_id + source_id pair.  Handles the rotated file automatically.
 */
class JSONL_Item_Extractor {

	/**
	 * Return all JSONL events for the given run and source page.
	 *
	 * Reads both the active log and the rotated (.1) file because a long run
	 * can span a rotation boundary.  Events from both files are merged and
	 * sorted by timestamp ascending.
	 *
	 * @param string $run_id    The run identifier (metg_YYYYMMDD_HHMMSS_XXXXXX).
	 * @param int    $source_id The source Elementor post ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_entries( string $run_id, int $source_id ): array {
		if ( '' === $run_id || $source_id <= 0 ) {
			return array();
		}

		$path    = Diagnostic_Logger::log_path();
		$rotated = $path . '.1';

		$entries = array();

		if ( @file_exists( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$entries = array_merge( $entries, self::extract_from_file( $path, $run_id, $source_id ) );
		}

		if ( @file_exists( $rotated ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$entries = array_merge( $entries, self::extract_from_file( $rotated, $run_id, $source_id ) );
		}

		if ( count( $entries ) <= 1 ) {
			return $entries;
		}

		// Deduplicate by timestamp+event, then sort ascending.
		$seen   = array();
		$unique = array();
		foreach ( $entries as $entry ) {
			$key = ( $entry['timestamp'] ?? '' ) . '|' . ( $entry['event'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$unique[]     = $entry;
		}

		usort(
			$unique,
			static function ( array $a, array $b ): int {
				return strcmp( (string) ( $a['timestamp'] ?? '' ), (string) ( $b['timestamp'] ?? '' ) );
			}
		);

		return $unique;
	}

	/**
	 * Read one JSONL file and collect matching events.
	 *
	 * @param string $path      Absolute path to the JSONL file.
	 * @param string $run_id    Target run identifier.
	 * @param int    $source_id Target source post ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function extract_from_file( string $path, string $run_id, int $source_id ): array {
		$entries = array();

		$handle = @fopen( $path, 'r' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return $entries;
		}

		try {
			while ( ( $line = fgets( $handle ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}

				$event = json_decode( $line, true );
				if ( ! is_array( $event ) ) {
					continue;
				}

				if ( ( $event['run_id'] ?? '' ) !== $run_id ) {
					continue;
				}

				// Only include item-level events that carry a source_id.
				if ( ! isset( $event['source_id'] ) ) {
					continue;
				}

				if ( (int) $event['source_id'] !== $source_id ) {
					continue;
				}

				$entries[] = $event;
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		return $entries;
	}
}
