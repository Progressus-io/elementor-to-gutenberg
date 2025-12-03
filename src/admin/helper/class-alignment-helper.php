<?php
/**
 * Centralized alignment helper for Elementor to Gutenberg conversions.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Utility class for normalizing and applying alignment values.
 */
class Alignment_Helper {

	/**
	 * Known Elementor alignment control keys ordered from most specific to least.
	 *
	 * The priority order is:
	 * 1. Explicit keys passed in $priority_keys (widget-specific overrides).
	 * 2. Known alignment control names below (widget-level before layout-level).
	 * 3. Fallback map (e.g. computed styles) provided by the caller.
	 *
	 * Widget-level settings intentionally override layout/section level values.
	 *
	 * @var array<int, string>
	 */
	private static array $known_keys = array(
		'flex_justify_content',
		'align',
		'alignment',
		'button_align',
		'content_position',
		'justify_content',
		'horizontal_align',
		'layout_align',
		'layout_justify_content',
		'icon_align',
		'image_align',
		'position',
		'text_align',
	);

	/**
	 * Detect alignment from Elementor settings and optional fallback map.
	 *
	 * @param array $settings Elementor settings array.
	 * @param array $priority_keys Keys to check before the known defaults.
	 * @param array $fallback_map Fallback map of alignment-like values (e.g. computed styles).
	 */
	public static function detect_alignment( array $settings, array $priority_keys = array(), array $fallback_map = array() ): string {
		$keys = array_merge( $priority_keys, self::$known_keys );

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			$normalized = self::normalize_alignment_value( $settings[ $key ] );
			if ( '' !== $normalized ) {
				return $normalized;
			}
		}

		foreach ( $fallback_map as $value ) {
			$normalized = self::normalize_alignment_value( $value );
			if ( '' !== $normalized ) {
				return $normalized;
			}
		}

		return '';
	}

	/**
	 * Normalize raw alignment values into canonical values.
	 *
	 * @param mixed $value Raw alignment value.
	 */
	public static function normalize_alignment_value( $value ): string {
		if ( is_array( $value ) ) {
			if ( isset( $value['value'] ) ) {
				$value = $value['value'];
			} elseif ( isset( $value['size'] ) ) {
				$value = $value['size'];
			}
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$clean = strtolower( trim( $value ) );
		if ( '' === $clean ) {
			return '';
		}

		$map = array(
			'left'          => 'left',
			'center'        => 'center',
			'right'         => 'right',
			'end'           => 'end',
			'start'         => 'start',
			'justify'       => 'justify',
			'justified'     => 'justify',
			'flex-start'    => 'start',
			'flex-end'      => 'end',
			'space-between' => 'justify',
			'space-around'  => 'justify',
			'space-evenly'  => 'justify',
			'middle'        => 'center',
		);

		return $map[ $clean ] ?? '';
	}

	/**
	 * Build reusable text alignment data arrays.
	 *
	 * @param string $alignment Canonical alignment value.
	 *
	 * @return array{attributes: array, classes: array, style: string}
	 */
	public static function build_text_alignment_payload( string $alignment ): array {
		if ( '' === $alignment ) {
			return array(
				'attributes' => array(),
				'classes'    => array(),
				'style'      => '',
			);
		}

		return array(
			'attributes' => array( 'textAlign' => $alignment ),
			'classes'    => array( 'has-text-align-' . $alignment ),
			'style'      => 'text-align:' . $alignment . ';',
		);
	}

	/**
	 * Build block alignment payload (align attribute + class helpers).
	 *
	 * @param string $alignment Canonical alignment value.
	 *
	 * @return array{attributes: array, classes: array}
	 */
	public static function build_block_alignment_payload( string $alignment ): array {
		if ( '' === $alignment ) {
			return array(
				'attributes' => array(),
				'classes'    => array(),
			);
		}

		$classes    = array();
		$clean_attr = Style_Parser::clean_class( 'align' . $alignment );

		if ( '' !== $clean_attr ) {
			$classes[] = $clean_attr;
		}

		return array(
			'attributes' => array( 'align' => $alignment ),
			'classes'    => $classes,
		);
	}

	/**
	 * Map text alignment to a CSS justify-content value for flex layouts.
	 *
	 * @param string $alignment Canonical alignment value.
	 */
	public static function map_justify_content( string $alignment ): string {
		switch ( $alignment ) {
			case 'center':
				return 'center';
			case 'right':
			case 'end':
				return 'flex-end';
			case 'justify':
				return 'space-between';
			case 'left':
			case 'start':
			default:
				return 'flex-start';
		}
	}
}
