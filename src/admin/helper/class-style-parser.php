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
		$attrs = array();
		$style = '';

		$typography_fields = array(
			'typography_font_family'     => array( 'attr' => 'fontFamily', 'style' => 'font-family' ),
			'typography_font_size'       => array( 'attr' => 'fontSize', 'style' => 'font-size', 'is_array' => true ),
			'typography_font_weight'     => array( 'attr' => 'fontWeight', 'style' => 'font-weight' ),
			'typography_line_height'     => array( 'attr' => 'lineHeight', 'style' => 'line-height', 'is_array' => true ),
			'typography_font_style'      => array( 'attr' => 'fontStyle', 'style' => 'font-style' ),
			'typography_text_decoration' => array( 'attr' => 'textDecoration', 'style' => 'text-decoration' ),
			'typography_letter_spacing'  => array( 'attr' => 'letterSpacing', 'style' => 'letter-spacing', 'is_array' => true ),
			'typography_word_spacing'    => array( 'attr' => 'wordSpacing', 'style' => 'word-spacing', 'is_array' => true ),
		);

		foreach ( $typography_fields as $key => $field ) {
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			if ( isset( $field['is_array'] ) && true === $field['is_array'] ) {
				$size = $settings[ $key ]['size'] ?? '';
				$unit = $settings[ $key ]['unit'] ?? ( 'typography_line_height' === $key ? '' : 'px' );
				if ( '' !== $size ) {
					$value = $size . $unit;
					$attrs[ $field['attr'] ] = $value;
					$style .= sprintf( '%s:%s;', $field['style'], esc_attr( $value ) );
				}
			} else {
				$attrs[ $field['attr'] ] = $settings[ $key ];
				$style .= sprintf( '%s:%s;', $field['style'], esc_attr( $settings[ $key ] ) );
			}
		}

		return array(
			'attributes' => $attrs,
			'style'      => $style,
		);
	}

	/**
	 * Parse spacing settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 * @return array Array containing style attributes for spacing.
	 */
	public static function parse_spacing( array $settings ): array {
		$attrs = array( 'style' => array( 'spacing' => array() ) );
		$spacing_types = array( 'margin', 'padding' );

		foreach ( $spacing_types as $spacing ) {
			$spacing_key = '_' . $spacing;
			if ( ! isset( $settings[ $spacing_key ] ) || ! is_array( $settings[ $spacing_key ] ) ) {
				continue;
			}

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $settings[ $spacing_key ][ $side ] ) ) {
					$attrs['style']['spacing'][ $spacing ][ $side ] = $settings[ $spacing_key ][ $side ] . ( $settings[ $spacing_key ]['unit'] ?? 'px' );
				}
			}
		}

		return $attrs;
	}

	public static function parse_border( array $settings ) {
		if ( isset( $settings['border_radius'] ) && is_array( $settings['border_radius'] ) ) {
			$unit = isset( $settings['border_radius']['unit'] ) ? $settings['border_radius']['unit'] : 'px';
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				
				if ( isset( $settings['border_radius'][ $side ] ) ) {
					$attrs['style']['border']['radius'][ $side ] = $settings['border_radius'][ $side ] . $unit;
				}
			}
		}

		if ( isset( $settings['border_border'] ) ) {
			$attrs['style']['border']['style'] = $settings['border_border'];
		}

		if ( isset( $settings['border_width'] ) && is_array( $settings['border_width'] ) ) {
			$unit = isset( $settings['border_width']['unit'] ) ? $settings['border_width']['unit'] : 'px';
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $settings['border_width'][ $side ] ) ) {
					$attrs['style']['border']['width'][ $side ] = $settings['border_width'][ $side ] . $unit;
				}
			}
		}

		return $attrs;
	}
}