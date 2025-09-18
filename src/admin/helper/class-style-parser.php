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
			'typography_text_transform'  => array( 'attr' => 'textTransform', 'style' => 'text-transform' ),
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

			if ( ! empty( $field['is_array'] ) ) {
				$size = $settings[ $key ]['size'] ?? '';
				$unit = $settings[ $key ]['unit'] ?? ( 'typography_line_height' === $key ? '' : 'px' );
				if ( '' !== $size && is_numeric( $size ) ) {
					$value = $size . $unit;
					$attrs[ $field['attr'] ] = $value;
					$style .= sprintf( '%s:%s;', $field['style'], esc_attr( $value ) );
				}
			} else {
				$value = $settings[ $key ];
				if ( '' !== $value ) {
					$attrs[ $field['attr'] ] = $value;
					$style .= sprintf( '%s:%s;', $field['style'], esc_attr( $value ) );
				}
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
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
	public static function parse_spacing( array $settings ): array {
		$attrs = array();
		$style = '';

		foreach ( array( 'padding', 'margin' ) as $spacing ) {
			$key = '_' . $spacing;
			if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				continue;
			}

			$unit = $settings[ $key ]['unit'] ?? 'px';

			foreach ( array( 'top', 'bottom', 'left', 'right' ) as $side ) {
				if ( isset( $settings[ $key ][ $side ] ) && '' !== $settings[ $key ][ $side ] ) {
					$value = $settings[ $key ][ $side ] . $unit;
					$attrs[ $spacing ][ $side ] = $value;
					$style .= sprintf( '%s-%s:%s;', $spacing, $side, esc_attr( $value ) );
				}
			}
		}

		return array(
			'attributes' => $attrs ? : array(),
			'style'      => $style,
		);
	}

	/**
	 * Parse border settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
	public static function parse_border( array $settings ): array {
		$attrs = array();
		$style = '';

		// Map Elementor sides to Gutenberg sides.
		$radius_map = array(
			'top'    => array( 'topLeft', 'top-left' ),
			'right'  => array( 'topRight', 'top-right' ),
			'bottom' => array( 'bottomRight', 'bottom-right' ),
			'left'   => array( 'bottomLeft', 'bottom-left' ),
		);

		// Border radius.
		if ( isset( $settings['_border_radius'] ) && is_array( $settings['_border_radius'] ) ) {
			$unit = $settings['_border_radius']['unit'] ?? 'px';
			foreach ( $radius_map as $el_side => $gb_side ) {
				if ( isset( $settings['_border_radius'][ $el_side ] ) && $settings['_border_radius'][ $el_side ] !== '' ) {
					$value = $settings['_border_radius'][ $el_side ] . $unit;
					$attrs['radius'][ $gb_side[0] ] = $value;
					$style .= sprintf( 'border-%s-radius:%s;', 
						$gb_side[1],
						esc_attr( $value )
					);
				}
			}
		}

		// Border width + color per side.
		if ( isset( $settings['_border_width'] ) && is_array( $settings['_border_width'] ) ) {
			$unit = $settings['_border_width']['unit'] ?? 'px';
			$color = ! empty( $settings['border_color'] ) ? strtolower( $settings['border_color'] ) : '';

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $settings['_border_width'][ $side ] ) && $settings['_border_width'][ $side ] !== '' ) {
					$width = $settings['_border_width'][ $side ] . $unit;
					$attrs[ $side ]['width'] = $width;
					$style .= sprintf( 'border-%s-width:%s;', $side, esc_attr( $width ) );

					if ( $color ) {
						$attrs[ $side ]['color'] = $color;
						$style .= sprintf( 'border-%s-color:%s;', $side, esc_attr( $color ) );
					}
				}
			}
		}

		// Border style (solid, dashed, etc.)
		if ( ! empty( $settings['_border_border'] ) ) {
			$attrs['style'] = $settings['_border_border'];
			$style .= 'border-style:' . esc_attr( $settings['_border_border'] ) . ';';
		}

		return array(
			'attributes' => $attrs ? : array(),
			'style'      => $style,
		);
	}


	/**
	 * Save custom CSS to the Customizer's Additional CSS.
	 *
	 * @param string $css The CSS string.
	 */
	public static function save_custom_css( string $css ) {
		$customizer_css_post = wp_get_custom_css_post();
		$existing_css        = $customizer_css_post ? $customizer_css_post->post_content : '';
		$new_css             = $existing_css . "\n" . $css;

		wp_update_custom_css_post( $new_css );
	}
}
