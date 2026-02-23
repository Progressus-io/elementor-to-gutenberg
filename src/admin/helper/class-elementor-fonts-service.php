<?php
/**
 * Elementor font utilities.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function get_option;
use function get_post_meta;
use function get_transient;
use function set_transient;
use function update_option;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor font service.
 */
class Elementor_Fonts_Service {
	/**
	 * Option name for cached font requirements.
	 */
	private const OPTION_NAME = 'progressus_gutenberg_font_requirements';

	/**
	 * Register font requirements from widget settings.
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return void
	 */
	public static function register_settings_fonts( array $settings ): void {
		if ( empty( $settings ) ) {
			return;
		}

		$families = array();

		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( false === strpos( $key, 'typography_font_family' ) ) {
				continue;
			}

			$family = self::apply_font_alias_map( self::normalize_font_family( (string) $value ) );
			if ( '' === $family || self::is_system_font( $family ) ) {
				continue;
			}

			$weight_key = str_replace( 'typography_font_family', 'typography_font_weight', $key );
			$weight     = Style_Parser::sanitize_font_weight_value( $settings[ $weight_key ] ?? '' );

			$families[ $family ] = array_filter( array( $weight ) );
		}

		if ( empty( $families ) ) {
			return;
		}

		self::merge_font_requirements( $families );
	}

	/**
	 * Get merged font requirements from Elementor kit and stored widgets.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function get_font_requirements(): array {
		$requirements = self::get_option_requirements();

		$body = Style_Parser::get_elementor_kit_typography( 'body' );
		$head = Style_Parser::get_elementor_kit_typography( 'headings' );

		$kit_fonts = array();
		self::collect_typography_font( $kit_fonts, $body, '400' );
		self::collect_typography_font( $kit_fonts, $head, '700' );

		if ( ! empty( $kit_fonts ) ) {
			$requirements = self::merge_requirements_arrays( $requirements, $kit_fonts );
		}

		return $requirements;
	}

	/**
	 * Build Google Fonts URL from per-post font schema.
	 *
	 * @param array $fonts Font map.
	 *
	 * @return string
	 */
	public static function build_google_fonts_url( array $fonts ): string {
		if ( empty( $fonts ) ) {
			return '';
		}

		$families = array();
		$keys     = array_keys( $fonts );
		natcasesort( $keys );

		foreach ( $keys as $family_key ) {
			$family = self::apply_font_alias_map( self::normalize_font_family( (string) $family_key ) );
			if ( '' === $family || self::is_system_font( $family ) ) {
				continue;
			}

			$data = isset( $fonts[ $family_key ] ) && is_array( $fonts[ $family_key ] ) ? $fonts[ $family_key ] : array();

			$weights = self::normalize_weights( $data['weights'] ?? $data );
			$italics = self::normalize_italics( $data['italics'] ?? array() );

			$family_query = str_replace( ' ', '+', $family );
			$variants     = self::build_variant_tuples( $weights, $italics );
			if ( ! empty( $variants ) ) {
				$family_query .= ':ital,wght@' . implode( ';', $variants );
			}

			$families[] = $family_query;
		}

		if ( empty( $families ) ) {
			return '';
		}

		return 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $families ) . '&display=swap';
	}

	/**
	 * Get cached fonts URL for a converted post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function get_post_fonts_url( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$fonts = get_post_meta( $post_id, '_etg_used_fonts', true );
		if ( ! is_array( $fonts ) || empty( $fonts ) ) {
			return '';
		}

		$stored_hash = (string) get_post_meta( $post_id, '_etg_used_fonts_hash', true );
		if ( '' === $stored_hash ) {
			$stored_hash = md5( (string) wp_json_encode( $fonts ) );
		}

		$cache_key = 'etg_fonts_url_' . $post_id . '_' . md5( $stored_hash . '|' . self::get_alias_map_version() );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$url = self::build_google_fonts_url( $fonts );
		set_transient( $cache_key, $url, DAY_IN_SECONDS );

		return $url;
	}

	/**
	 * Normalize font-family values by taking first family and cleaning it.
	 *
	 * @param string $raw Raw family value.
	 *
	 * @return string
	 */
	public static function normalize_font_family( string $raw ): string {
		$family = Style_Parser::sanitize_font_family_value( $raw );
		if ( '' === $family ) {
			return '';
		}

		$parts  = array_map( 'trim', explode( ',', $family ) );
		$family = trim( (string) ( $parts[0] ?? '' ), "\"' " );
		$family = preg_replace( '/\s+/', ' ', $family );
		$family = is_string( $family ) ? trim( $family ) : '';

		$generic = array(
			'serif',
			'sans-serif',
			'monospace',
		);

		if ( in_array( strtolower( $family ), $generic, true ) ) {
			$family = strtolower( $family );
		}

		if ( in_array( strtolower( $family ), array( 'inherit', 'initial', 'unset' ), true ) ) {
			return '';
		}

		return $family;
	}

	/**
	 * Apply alias map for common family mistakes.
	 *
	 * @param string $family Normalized family.
	 *
	 * @return string
	 */
	public static function apply_font_alias_map( string $family ): string {
		$family = trim( $family );
		if ( '' === $family ) {
			return '';
		}

		$aliases = self::get_font_alias_map();
		$key     = strtolower( $family );

		if ( isset( $aliases[ $key ] ) ) {
			$family = trim( (string) $aliases[ $key ] );
		}

		return self::normalize_font_family( $family );
	}

	/**
	 * Determine if a font family is a system font.
	 *
	 * @param string $family Font family name.
	 *
	 * @return bool
	 */
	public static function is_system_font( string $family ): bool {
		$family = strtolower( trim( $family ) );
		$system = array(
			'inherit',
			'initial',
			'serif',
			'sans-serif',
			'monospace',
			'cursive',
			'fantasy',
			'system-ui',
			'ui-sans-serif',
			'ui-serif',
			'ui-monospace',
			'ui-rounded',
		);

		return in_array( $family, $system, true );
	}

	/**
	 * Merge new font requirements into the stored option.
	 *
	 * @param array<string, array<int, string>> $families Family map.
	 *
	 * @return void
	 */
	private static function merge_font_requirements( array $families ): void {
		$current = self::get_option_requirements();
		$merged  = self::merge_requirements_arrays( $current, $families );

		update_option( self::OPTION_NAME, $merged, false );
	}

	/**
	 * Retrieve stored font requirements from options.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function get_option_requirements(): array {
		$stored = get_option( self::OPTION_NAME );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$output = array();
		foreach ( $stored as $family => $weights ) {
			if ( ! is_array( $weights ) ) {
				$weights = array();
			}
			$output[ (string) $family ] = array_values( array_unique( array_map( 'strval', $weights ) ) );
		}

		return $output;
	}

	/**
	 * Merge multiple requirements arrays.
	 *
	 * @param array<string, array<int, string>> $base Base requirements.
	 * @param array<string, array<int, string>> $extra Extra requirements.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function merge_requirements_arrays( array $base, array $extra ): array {
		foreach ( $extra as $family => $weights ) {
			$family = trim( (string) $family );
			if ( '' === $family ) {
				continue;
			}

			if ( ! isset( $base[ $family ] ) ) {
				$base[ $family ] = array();
			}

			$weights         = is_array( $weights ) ? $weights : array();
			$base[ $family ] = array_values( array_unique( array_merge( $base[ $family ], $weights ) ) );
		}

		return $base;
	}

	/**
	 * Collect a font requirement from typography settings.
	 *
	 * @param array<string, array<int, string>> $requirements Requirements map.
	 * @param array $settings Typography settings.
	 * @param string $default_weight Default weight when missing.
	 *
	 * @return void
	 */
	private static function collect_typography_font( array &$requirements, array $settings, string $default_weight ): void {
		$family = self::apply_font_alias_map( self::normalize_font_family( (string) ( $settings['typography_font_family'] ?? '' ) ) );
		if ( '' === $family || self::is_system_font( $family ) ) {
			return;
		}

		$weight = Style_Parser::sanitize_font_weight_value( $settings['typography_font_weight'] ?? '' );
		if ( '' === $weight ) {
			$weight = $default_weight;
		}

		if ( ! isset( $requirements[ $family ] ) ) {
			$requirements[ $family ] = array();
		}

		if ( '' !== $weight ) {
			$requirements[ $family ][] = $weight;
		}
	}

	/**
	 * Get alias map from option as JSON or key/value lines.
	 *
	 * @return array<string, string>
	 */
	private static function get_font_alias_map(): array {
		$raw = get_option( 'etg_font_alias_map', '' );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$aliases = array();
			foreach ( $decoded as $wrong => $right ) {
				$wrong = strtolower( trim( (string) $wrong ) );
				$right = trim( (string) $right );
				if ( '' === $wrong || '' === $right ) {
					continue;
				}
				$aliases[ $wrong ] = $right;
			}
			return $aliases;
		}

		$aliases = array();
		$lines   = preg_split( '/\r\n|\r|\n/', $raw );
		$lines   = is_array( $lines ) ? $lines : array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$parts = preg_split( '/\s*(=>|=|:)\s*/', $line, 2 );
			if ( ! is_array( $parts ) || 2 !== count( $parts ) ) {
				continue;
			}

			$wrong = strtolower( trim( (string) $parts[0] ) );
			$right = trim( (string) $parts[1] );
			if ( '' === $wrong || '' === $right ) {
				continue;
			}

			$aliases[ $wrong ] = $right;
		}

		return $aliases;
	}

	/**
	 * Build deterministic transient namespace version.
	 *
	 * @return string
	 */
	private static function get_alias_map_version(): string {
		$version = get_option( 'etg_font_alias_map_version', '' );
		if ( is_string( $version ) && '' !== trim( $version ) ) {
			return trim( $version );
		}

		$map = get_option( 'etg_font_alias_map', '' );
		return md5( is_string( $map ) ? $map : '' );
	}

	/**
	 * Normalize requested weights.
	 *
	 * @param mixed $weights Weights payload.
	 *
	 * @return array<int, string>
	 */
	private static function normalize_weights( $weights ): array {
		$weights = is_array( $weights ) ? $weights : array();
		$output  = array();
		foreach ( $weights as $weight ) {
			$clean = Style_Parser::sanitize_font_weight_value( $weight );
			if ( '' === $clean ) {
				continue;
			}
			$output[ $clean ] = true;
		}

		if ( empty( $output ) ) {
			$output['400'] = true;
		}

		$keys = array_keys( $output );
		sort( $keys, SORT_NATURAL );
		return array_values( $keys );
	}

	/**
	 * Normalize requested italic values.
	 *
	 * @param mixed $italics Italics payload.
	 *
	 * @return array<int, string>
	 */
	private static function normalize_italics( $italics ): array {
		$italics = is_array( $italics ) ? $italics : array();
		$output  = array();

		foreach ( $italics as $italic ) {
			$italic = (string) $italic;
			if ( '1' === $italic ) {
				$output['1'] = true;
			} elseif ( '0' === $italic ) {
				$output['0'] = true;
			}
		}

		if ( empty( $output ) ) {
			$output['0'] = true;
		}

		$keys = array_keys( $output );
		sort( $keys, SORT_NATURAL );
		return array_values( $keys );
	}

	/**
	 * Build variant tuples for css2 API.
	 *
	 * @param array<int, string> $weights Requested weights.
	 * @param array<int, string> $italics Requested italic axes.
	 *
	 * @return array<int, string>
	 */
	private static function build_variant_tuples( array $weights, array $italics ): array {
		if ( empty( $weights ) ) {
			return array();
		}

		$tuples = array();
		foreach ( $italics as $italic ) {
			if ( '0' !== $italic && '1' !== $italic ) {
				continue;
			}

			foreach ( $weights as $weight ) {
				$tuples[] = $italic . ',' . $weight;
			}
		}

		return $tuples;
	}
}
