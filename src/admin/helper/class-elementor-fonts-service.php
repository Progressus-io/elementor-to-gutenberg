<?php
/**
 * Elementor kit font loader utilities.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function get_option;
use function update_option;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor kit font service.
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

			$family = self::normalize_font_family( $value );
			if ( '' === $family ) {
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
	 * Build a Google Fonts URL from requirements.
	 *
	 * @param array<string, array<int, string>> $requirements Font requirements.
	 *
	 * @return string
	 */
	public static function build_google_fonts_url( array $requirements ): string {
		if ( empty( $requirements ) ) {
			return '';
		}

		$families = array();
		foreach ( $requirements as $family => $weights ) {
			$family = trim( (string) $family );
			if ( '' === $family || self::is_system_font( $family ) ) {
				continue;
			}

			$weights = array_values( array_unique( array_filter( array_map( 'trim', $weights ) ) ) );
			sort( $weights );

			$family_query = str_replace( ' ', '+', $family );
			if ( ! empty( $weights ) ) {
				$family_query .= ':wght@' . implode( ';', $weights );
			}

			$families[] = $family_query;
		}

		if ( empty( $families ) ) {
			return '';
		}

		return 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $families ) . '&display=swap';
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
		$family = self::normalize_font_family( $settings['typography_font_family'] ?? '' );
		if ( '' === $family ) {
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
	 * Normalize font-family values by taking the first family.
	 *
	 * @param mixed $value Font family value.
	 *
	 * @return string
	 */
	private static function normalize_font_family( $value ): string {
		$family = Style_Parser::sanitize_font_family_value( $value );
		if ( '' === $family ) {
			return '';
		}

		$parts  = array_map( 'trim', explode( ',', $family ) );
		$family = trim( (string) ( $parts[0] ?? '' ), "\"' " );

		return $family;
	}

	/**
	 * Determine if a font family is a system font.
	 *
	 * @param string $family Font family name.
	 *
	 * @return bool
	 */
	private static function is_system_font( string $family ): bool {
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
}
