<?php
/**
 * Utility class for parsing styles and attributes.
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin\Helper;

use function sanitize_html_class;
use function sanitize_hex_color;
use function wp_get_global_settings;

defined( 'ABSPATH' ) || exit;

/**
 * Utility class for parsing styles and attributes.
 */
class Style_Parser {

	/**
	 * Cached theme palette colors.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private static ?array $theme_palette = null;

	/**
	 * Cached theme font-size presets.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private static ?array $font_sizes = null;

	/**
	 * Parse typography settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
	public static function parse_typography( array $settings ): array {
		$attributes  = array();
		$style_parts = array();
		$fields      = array(
			'typography_font_family'     => array( 'attr' => 'fontFamily', 'css' => 'font-family' ),
			'typography_text_transform'  => array( 'attr' => 'textTransform', 'css' => 'text-transform' ),
			'typography_font_style'      => array( 'attr' => 'fontStyle', 'css' => 'font-style' ),
			'typography_font_weight'     => array( 'attr' => 'fontWeight', 'css' => 'font-weight' ),
			'typography_text_decoration' => array( 'attr' => 'textDecoration', 'css' => 'text-decoration' ),
		);

		foreach ( $fields as $key => $map ) {
			$value = self::sanitize_scalar( $settings[ $key ] ?? null );
			if ( '' === $value ) {
				continue;
			}

			$attributes[ $map['attr'] ] = $value;
			$style_parts[]              = sprintf( '%s:%s;', $map['css'], $value );
		}

		$dimensions = array(
			'typography_font_size'      => array( 'attr' => 'fontSize', 'css' => 'font-size', 'default_unit' => 'px' ),
			'typography_line_height'    => array(
				'attr'         => 'lineHeight',
				'css'          => 'line-height',
				'default_unit' => ''
			),
			'typography_letter_spacing' => array(
				'attr'         => 'letterSpacing',
				'css'          => 'letter-spacing',
				'default_unit' => 'px'
			),
			'typography_word_spacing'   => array(
				'attr'         => 'wordSpacing',
				'css'          => 'word-spacing',
				'default_unit' => 'px'
			),
		);

		foreach ( $dimensions as $key => $map ) {
			$value = self::normalize_dimension( $settings[ $key ] ?? null, $map['default_unit'] );
			if ( null === $value ) {
				continue;
			}

			$attributes[ $map['attr'] ] = $value;
			$style_parts[]              = sprintf( '%s:%s;', $map['css'], $value );
		}

		return array(
			'attributes' => $attributes,
			'style'      => implode( '', $style_parts ),
		);
	}

	/**
	 * Retrieve computed styles from an Elementor element.
	 *
	 * @param array $element Elementor element data.
	 *
	 * @return array<string, string> Normalized property => value map.
	 */
	public static function get_computed_styles( array $element ): array {
		$candidates = array();

		foreach ( array( 'computed_styles', 'computedStyles', 'computed' ) as $key ) {
			if ( isset( $element[ $key ] ) && is_array( $element[ $key ] ) ) {
				$candidates[] = $element[ $key ];
			}
		}

		if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
			foreach ( array( 'computed_styles', 'computedStyles', 'computed' ) as $key ) {
				if ( isset( $element['settings'][ $key ] ) && is_array( $element['settings'][ $key ] ) ) {
					$candidates[] = $element['settings'][ $key ];
				}
			}
		}

		$styles = array();

		foreach ( $candidates as $candidate ) {
			foreach ( self::flatten_style_candidate( $candidate ) as $property => $value ) {
				if ( '' === $property || '' === $value ) {
					continue;
				}

				$styles[ $property ] = $value;
			}
		}

		return $styles;
	}

	/**
	 * Attempt to match a color to a theme preset slug within a 3% delta.
	 *
	 * @param string $color Color string.
	 *
	 * @return string|null Preset slug or null when no close match exists.
	 */
	public static function match_theme_color_slug( string $color ): ?string {
		$rgb = self::parse_color_to_rgb( $color );
		if ( null === $rgb ) {
			return null;
		}

		$palette = self::get_theme_palette();
		if ( empty( $palette ) ) {
			return null;
		}

		$closest_slug   = null;
		$closest_delta  = null;
		$max_difference = sqrt( 3 * ( 255 ** 2 ) );

		foreach ( $palette as $preset ) {
			$preset_color = self::parse_color_to_rgb( $preset['color'] ?? '' );
			if ( null === $preset_color ) {
				continue;
			}

			$distance = sqrt(
				( $rgb[0] - $preset_color[0] ) ** 2 +
				( $rgb[1] - $preset_color[1] ) ** 2 +
				( $rgb[2] - $preset_color[2] ) ** 2
			);

			$delta = $distance / $max_difference;

			if ( $delta > 0.03 ) {
				continue;
			}

			if ( null === $closest_delta || $delta < $closest_delta ) {
				$closest_delta = $delta;
				$closest_slug  = $preset['slug'] ?? null;
			}
		}

		return $closest_slug;
	}

	/**
	 * Attempt to match a font-size to a theme preset slug.
	 *
	 * @param string $font_size Font-size value (e.g. 18px, 1.125rem).
	 *
	 * @return string|null Preset slug when the value is within a 3% tolerance of a preset.
	 */
	public static function match_font_size_slug( string $font_size ): ?string {
		$target = self::font_size_to_pixels( $font_size );
		if ( null === $target ) {
			return null;
		}

		$presets = self::get_font_size_presets();
		if ( empty( $presets ) ) {
			return null;
		}

		foreach ( $presets as $preset ) {
			$preset_value = self::font_size_to_pixels( $preset['size'] ?? '' );
			if ( null === $preset_value ) {
				continue;
			}

			$tolerance = max( 0.25, $preset_value * 0.03 );

			if ( abs( $preset_value - $target ) <= $tolerance ) {
				return $preset['slug'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Resolve a theme color value from a preset slug.
	 *
	 * @param string $slug Theme color slug.
	 *
	 * @return string Normalized hexadecimal color or empty string when unavailable.
	 */
	public static function resolve_theme_color_value( string $slug ): string {
		$slug = self::sanitize_scalar( $slug );
		if ( '' === $slug ) {
			return '';
		}

		foreach ( self::get_theme_palette() as $preset ) {
			if ( strtolower( $preset['slug'] ?? '' ) !== strtolower( $slug ) ) {
				continue;
			}

			$normalized = self::normalize_color_value( $preset['color'] ?? '' );
			if ( '' !== $normalized ) {
				return $normalized;
			}
		}

		return '';
	}

	/**
	 * Resolve a font-size value from a preset slug.
	 *
	 * @param string $slug Theme font size slug.
	 *
	 * @return string Sanitized font-size string or empty string when unavailable.
	 */
	public static function resolve_font_size_value( string $slug ): string {
		$slug = self::sanitize_scalar( $slug );
		if ( '' === $slug ) {
			return '';
		}

		foreach ( self::get_font_size_presets() as $preset ) {
			if ( strtolower( $preset['slug'] ?? '' ) !== strtolower( $slug ) ) {
				continue;
			}

			$value = self::sanitize_css_dimension_value( $preset['size'] ?? '' );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Sanitize a CSS dimension value allowing typical units.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_css_dimension_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( '0' === $value || '0px' === strtolower( $value ) ) {
			return '0';
		}

		if ( preg_match( '/^-?[0-9]*\.?[0-9]+(px|em|rem|vh|vw|%)?$/i', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a font-family value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_font_family_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}

	/**
	 * Sanitize a font-style value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_font_style_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value    = strtolower( trim( (string) $value ) );
		$allowed  = array( 'normal', 'italic', 'oblique', 'inherit', 'initial', 'unset' );
		$fallback = '';

		foreach ( $allowed as $option ) {
			if ( $value === $option ) {
				return $option;
			}
		}

		return $fallback;
	}

	/**
	 * Sanitize a text-decoration value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_text_decoration_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value   = strtolower( trim( (string) $value ) );
		$allowed = array(
			'none',
			'underline',
			'overline',
			'line-through',
			'inherit',
			'initial',
			'unset',
		);

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Sanitize a letter-spacing value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_letter_spacing_value( $value ): string {
		$sanitized = self::sanitize_css_dimension_value( $value );

		return $sanitized;
	}

	/**
	 * Sanitize a word-spacing value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_word_spacing_value( $value ): string {
		$sanitized = self::sanitize_css_dimension_value( $value );

		return $sanitized;
	}

	/**
	 * Sanitize a line-height value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_line_height_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$keywords = array( 'normal', 'inherit', 'initial', 'unset' );
		if ( in_array( $value, $keywords, true ) ) {
			return $value;
		}

		if ( preg_match( '/^-?[0-9]*\.?[0-9]+(px|em|rem|%)?$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a font-weight value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_font_weight_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$keywords = array( 'normal', 'bold', 'lighter', 'bolder', 'inherit', 'initial', 'unset' );
		if ( in_array( $value, $keywords, true ) ) {
			return $value;
		}

		if ( preg_match( '/^(100|200|300|400|500|600|700|800|900)$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a text-transform value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_text_transform_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$keywords = array( 'uppercase', 'lowercase', 'capitalize', 'none', 'inherit', 'initial', 'unset' );
		if ( in_array( $value, $keywords, true ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Normalize a color string to hexadecimal when possible.
	 *
	 * @param mixed $color Raw color value.
	 */
	public static function normalize_color_value( $color ): string {
		if ( is_array( $color ) || is_object( $color ) ) {
			return '';
		}

		$color = trim( strtolower( (string) $color ) );
		if ( '' === $color || 0 === strpos( $color, 'var(' ) || 'transparent' === $color ) {
			return '';
		}

		$hex = sanitize_hex_color( $color );
		if ( false !== $hex && null !== $hex ) {
			return strtolower( $hex );
		}

		$rgb = self::parse_color_to_rgb( $color );
		if ( null === $rgb ) {
			return '';
		}

		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Convert raw palette data into a flat list.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_theme_palette(): array {
		if ( null !== self::$theme_palette ) {
			return self::$theme_palette;
		}

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			self::$theme_palette = array();

			return self::$theme_palette;
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$output  = array();

		foreach ( array( 'theme', 'custom', 'default' ) as $group ) {
			if ( empty( $palette[ $group ] ) || ! is_array( $palette[ $group ] ) ) {
				continue;
			}

			foreach ( $palette[ $group ] as $preset ) {
				if ( empty( $preset['slug'] ) || empty( $preset['color'] ) ) {
					continue;
				}

				$output[] = array(
					'slug'  => (string) $preset['slug'],
					'color' => (string) $preset['color'],
				);
			}
		}

		self::$theme_palette = $output;

		return self::$theme_palette;
	}

	/**
	 * Fetch font size presets defined by the active theme.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_font_size_presets(): array {
		if ( null !== self::$font_sizes ) {
			return self::$font_sizes;
		}

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			self::$font_sizes = array();

			return self::$font_sizes;
		}

		$settings = wp_get_global_settings( array( 'typography', 'fontSizes' ) );
		$output   = array();

		foreach ( array( 'theme', 'custom', 'default' ) as $group ) {
			if ( empty( $settings[ $group ] ) || ! is_array( $settings[ $group ] ) ) {
				continue;
			}

			foreach ( $settings[ $group ] as $preset ) {
				if ( empty( $preset['slug'] ) || empty( $preset['size'] ) ) {
					continue;
				}

				$output[] = array(
					'slug' => (string) $preset['slug'],
					'size' => (string) $preset['size'],
				);
			}
		}

		self::$font_sizes = $output;

		return self::$font_sizes;
	}

	/**
	 * Convert font-size values to an approximate pixel value.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function font_size_to_pixels( $value ): ?float {
		if ( is_array( $value ) || is_object( $value ) ) {
			return null;
		}

		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)px$/', $value, $matches ) ) {
			return (float) $matches[1];
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)rem$/', $value, $matches ) ) {
			return (float) $matches[1] * 16;
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)em$/', $value, $matches ) ) {
			return (float) $matches[1] * 16;
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)$/', $value, $matches ) ) {
			return (float) $matches[1];
		}

		return null;
	}

	/**
	 * Convert mixed style data into a property => value map.
	 *
	 * @param mixed $candidate Potential style structure.
	 *
	 * @return array<string, string>
	 */
	private static function flatten_style_candidate( $candidate ): array {
		$output = array();

		if ( ! is_array( $candidate ) ) {
			return $output;
		}

		foreach ( $candidate as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['property'], $value['value'] ) ) {
					$property = self::normalize_property_name( $value['property'] );
					$val      = trim( (string) $value['value'] );
					if ( '' !== $property && '' !== $val ) {
						$output[ $property ] = $val;
					}
				}

				continue;
			}

			$property = self::normalize_property_name( (string) $key );
			$val      = trim( (string) $value );
			if ( '' === $property || '' === $val ) {
				continue;
			}

			$output[ $property ] = $val;
		}

		return $output;
	}

	/**
	 * Normalize property names to kebab-case.
	 *
	 * @param string $property Property name.
	 */
	private static function normalize_property_name( string $property ): string {
		$property = trim( (string) $property );
		if ( '' === $property ) {
			return '';
		}

		$property = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $property ) );
		$property = str_replace( array( '_', ' ' ), '-', $property );

		return trim( $property );
	}

	/**
	 * Convert color strings (hex/rgb/rgba) to RGB array.
	 *
	 * @param mixed $color Raw color value.
	 *
	 * @return array{0:int,1:int,2:int}|null
	 */
	private static function parse_color_to_rgb( $color ): ?array {
		if ( is_array( $color ) || is_object( $color ) ) {
			return null;
		}

		$color = trim( strtolower( (string) $color ) );
		if ( '' === $color ) {
			return null;
		}

		$hex = sanitize_hex_color( $color );
		if ( $hex ) {
			$hex = ltrim( $hex, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}

			if ( 6 === strlen( $hex ) ) {
				return array(
					hexdec( substr( $hex, 0, 2 ) ),
					hexdec( substr( $hex, 2, 2 ) ),
					hexdec( substr( $hex, 4, 2 ) ),
				);
			}
		}

		if ( preg_match( '/^rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)$/', $color, $matches ) ) {
			return array( (int) $matches[1], (int) $matches[2], (int) $matches[3] );
		}

		if ( preg_match( '/^rgba\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]*\.?[0-9]+)\s*\)$/', $color, $matches ) ) {
			$alpha = (float) $matches[4];
			if ( $alpha <= 0 ) {
				return null;
			}

			return array( (int) $matches[1], (int) $matches[2], (int) $matches[3] );
		}

		return null;
	}

	/**
	 * Parse spacing settings from Elementor settings.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return array{attributes:array, style:string}
	 */
	public static function parse_spacing( array $settings ): array {
		$attributes  = array();
		$style_parts = array();
		$maps        = array(
			'_padding' => 'padding',
			'_margin'  => 'margin',
			'padding'  => 'padding',
			'margin'   => 'margin',
		);

		foreach ( $maps as $key => $type ) {
			$data = $settings[ $key ] ?? null;
			if ( ! is_array( $data ) ) {
				continue;
			}

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$value = self::extract_box_value( $data, $side );
				if ( null === $value ) {
					continue;
				}

				$attributes[ $type ][ $side ] = $value;
				$style_parts[]                = sprintf( '%s-%s:%s;', $type, $side, $value );
			}
		}

		$gap_keys = array( 'gap', 'column_gap', 'gap_columns' );
		foreach ( $gap_keys as $gap_key ) {
			$gap_value = self::normalize_dimension( $settings[ $gap_key ] ?? null, 'px' );
			if ( null === $gap_value ) {
				continue;
			}

			$attributes['blockGap'] = $gap_value;
			$style_parts[]          = sprintf( 'gap:%s;', $gap_value );
			break;
		}

		return array(
			'attributes' => $attributes,
			'style'      => implode( '', $style_parts ),
		);
	}

	/**
	 * Parse border settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
	public static function parse_border( array $settings ): array {
		$attributes  = array();
		$style_parts = array();

		$radius_sources = array( '_border_radius', 'border_radius' );
		foreach ( $radius_sources as $radius_key ) {
			$radius_data = $settings[ $radius_key ] ?? null;
			if ( ! is_array( $radius_data ) ) {
				continue;
			}

			$unit = self::sanitize_scalar( $radius_data['unit'] ?? 'px' );
			foreach (
				array(
					'topLeft'     => 'top',
					'topRight'    => 'right',
					'bottomRight' => 'bottom',
					'bottomLeft'  => 'left',
				) as $attr_key => $side
			) {
				$value = self::normalize_dimension( $radius_data[ $side ] ?? null, $unit );
				if ( null === $value ) {
					continue;
				}

				$attributes['radius'][ $attr_key ] = $value;
				$style_parts[]                     = sprintf( 'border-%s-radius:%s;', str_replace( array(
					'Left',
					'Right'
				), array( 'left', 'right' ), strtolower( preg_replace( '/([A-Z])/', '-$1', $attr_key ) ) ), $value );
			}
		}

		$width_sources = array( '_border_width', 'border_width' );
		$color         = self::sanitize_color( $settings['border_color'] ?? $settings['_border_color'] ?? '' );

		foreach ( $width_sources as $width_key ) {
			$width_data = $settings[ $width_key ] ?? null;
			if ( ! is_array( $width_data ) ) {
				continue;
			}

			$unit = self::sanitize_scalar( $width_data['unit'] ?? 'px' );
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$value = self::normalize_dimension( $width_data[ $side ] ?? null, $unit );
				if ( null === $value ) {
					continue;
				}

				$attributes[ $side ]['width'] = $value;
				$style_parts[]                = sprintf( 'border-%s-width:%s;', $side, $value );

				if ( '' !== $color ) {
					$attributes[ $side ]['color'] = $color;
					$style_parts[]                = sprintf( 'border-%s-color:%s;', $side, $color );
				}
			}
		}

		$style_value = self::sanitize_scalar( $settings['_border_border'] ?? $settings['border_style'] ?? '' );
		if ( '' !== $style_value ) {
			$attributes['style'] = $style_value;
			$style_parts[]       = sprintf( 'border-style:%s;', $style_value );
		}

		return array(
			'attributes' => $attributes,
			'style'      => implode( '', $style_parts ),
		);
	}

	/**
	 * Parse container specific styles into block attributes.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return array
	 */
	public static function parse_container_styles( array $settings ): array {
		$attributes = array();

		$spacing = self::parse_spacing( $settings );
		if ( ! empty( $spacing['attributes'] ) ) {
			$attributes['style']['spacing'] = $spacing['attributes'];
		}

		$border = self::parse_border( $settings );
		if ( ! empty( $border['attributes'] ) ) {
			$attributes['style']['border'] = $border['attributes'];
		}

		$background = self::sanitize_color( $settings['background_color'] ?? $settings['_background_color'] ?? '' );
		if ( '' !== $background ) {
			if ( self::is_preset_slug( $background ) ) {
				$attributes['backgroundColor'] = $background;
				$attributes['className']       = self::append_class( $attributes['className'] ?? '', 'has-background' );
			} else {
				$attributes['style']['color']['background'] = $background;
				$attributes['className']                    = self::append_class( $attributes['className'] ?? '', 'has-background' );
			}
		}

		$custom_classes = self::sanitize_class_string( $settings['_css_classes'] ?? '' );
		if ( '' !== $custom_classes ) {
			$attributes['className'] = self::append_class( $attributes['className'] ?? '', $custom_classes );
		}

		return $attributes;
	}

	/**
	 * Determine if the supplied color is a preset slug.
	 *
	 * @param string $color Color string.
	 */
	private static function is_preset_slug( string $color ): bool {
		return '' !== $color && false === strpos( $color, '#' ) && 0 !== strpos( $color, 'rgb' );
	}

	/**
	 * Sanitize a scalar value from Elementor settings.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function sanitize_scalar( $value ): string {
		if ( is_array( $value ) ) {
			return '';
		}

		$value = is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value;

		return trim( $value );
	}

	/**
	 * Normalize color strings.
	 *
	 * @param mixed $value Potential color value.
	 */
	private static function sanitize_color( $value ): string {
		if ( is_array( $value ) ) {
			$value = $value['value'] ?? $value['color'] ?? '';
		}

		return strtolower( self::sanitize_scalar( $value ) );
	}

	/**
	 * Normalize Elementor dimension value.
	 *
	 * @param mixed $value Raw value.
	 * @param string $default_unit Default unit when missing.
	 */
	private static function normalize_dimension( $value, string $default_unit ): ?string {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) ) {
				return self::normalize_dimension( $value['size'], $value['unit'] ?? $default_unit );
			}
			if ( isset( $value['value'] ) ) {
				return self::normalize_dimension( $value['value'], $value['unit'] ?? $default_unit );
			}
		}

		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return $value . ( '' === $default_unit ? '' : $default_unit );
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/[a-z%]+$/i', $value ) ) {
			return $value;
		}

		return $value . ( '' === $default_unit ? '' : $default_unit );
	}

	/**
	 * Extract padding/margin side values.
	 *
	 * @param array $data Elementor box model array.
	 * @param string $side Side to extract.
	 */
	private static function extract_box_value( array $data, string $side ): ?string {
		if ( array_key_exists( $side, $data ) ) {
			return self::normalize_dimension( $data[ $side ], $data['unit'] ?? 'px' );
		}

		return null;
	}

	/**
	 * Append classes safely, ensuring no duplicates.
	 *
	 * @param string $existing Existing class list.
	 * @param string $new New class or classes.
	 */
	private static function append_class( string $existing, string $new ): string {
		$existing_list = '' === $existing ? array() : preg_split( '/\s+/', $existing );
		$new_list      = preg_split( '/\s+/', $new );
		$combined      = array();

		foreach ( array_merge( (array) $existing_list, (array) $new_list ) as $class ) {
			$class = trim( (string) $class );
			if ( '' === $class ) {
				continue;
			}
			$combined[ $class ] = true;
		}

		return implode( ' ', array_keys( $combined ) );
	}

	/**
	 * Sanitize custom class string from Elementor.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function sanitize_class_string( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$classes = array();
		foreach ( preg_split( '/\s+/', $value ) as $class ) {
			$sanitized = self::clean_class( $class );
			if ( '' === $sanitized ) {
				continue;
			}
			$classes[] = $sanitized;
		}

		return implode( ' ', $classes );
	}

	/**
	 * Sanitize a single class name while dropping Elementor-generated classes.
	 *
	 * @param string $class Raw class name.
	 *
	 * @return string Sanitized class name or empty string if disallowed.
	 */
	public static function clean_class( string $class ): string {
		$class = trim( $class );
		if ( '' === $class ) {
			return '';
		}

		$sanitized = sanitize_html_class( $class );
		if ( '' === $sanitized ) {
			return '';
		}

		if ( self::is_disallowed_elementor_class( $sanitized ) ) {
			return '';
		}

		return $sanitized;
	}

	/**
	 * Determine if a class should be stripped because it's Elementor-specific.
	 *
	 * @param string $class Sanitized class name.
	 */
	private static function is_disallowed_elementor_class( string $class ): bool {
		$blocked_exact = array(
			'e-con',
			'e-con-full',
			'e-con-boxed',
			'e-con-child',
			'e-grid',
		);

		if ( in_array( $class, $blocked_exact, true ) ) {
			return true;
		}

		$blocked_prefixes = array(
			'elementor',
			'elementor-',
			'elementor_',
			'e-con-',
			'e-grid-',
			'wp-elements-',
		);

		foreach ( $blocked_prefixes as $prefix ) {
			if ( 0 === strpos( $class, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Save custom CSS to the Customizer's Additional CSS store when available.
	 *
	 * @param string $css CSS string to append.
	 */
	public static function save_custom_css( string $css ): void {
		$css = trim( $css );
		if ( '' === $css ) {
			return;
		}

		if ( ! function_exists( 'wp_get_custom_css_post' ) || ! function_exists( 'wp_update_custom_css_post' ) ) {
			return;
		}

		$customizer_css_post = wp_get_custom_css_post();
		$existing_css        = $customizer_css_post ? (string) $customizer_css_post->post_content : '';
		$new_css             = rtrim( $existing_css ) . "\n" . $css;

		wp_update_custom_css_post( $new_css );
	}
}
