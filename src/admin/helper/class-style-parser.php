<?php
/**
 * Utility class for parsing styles and attributes.
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Utility class for parsing styles and attributes.
 */
class Style_Parser {

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
			$class = trim( $class );
			if ( '' === $class ) {
				continue;
			}
			$classes[] = sanitize_html_class( $class );
		}

		return implode( ' ', $classes );
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
