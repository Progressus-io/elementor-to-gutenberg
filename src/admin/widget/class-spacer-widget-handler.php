<?php
/**
 * Widget handler for Elementor spacer widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor spacer widget.
 */
class Spacer_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor spacer to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

		$height = $this->resolve_height( $settings );

		$custom_class_raw = isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '';
		$custom_id        = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_css       = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';

		$custom_classes = array();
		if ( '' !== trim( $custom_class_raw ) ) {
			foreach ( preg_split( '/\s+/', trim( $custom_class_raw ) ) as $class ) {
				$clean = Style_Parser::clean_class( (string) $class );
				if ( '' === $clean ) {
					continue;
				}
				$custom_classes[] = $clean;
			}
		}

		$class_parts = array_merge( array( 'wp-block-spacer' ), $custom_classes );
		$class_parts = array_values( array_unique( array_filter( $class_parts ) ) );
		$class_attr  = implode( ' ', $class_parts );

		$attrs = wp_json_encode(
			array(
				'height' => $height,
			)
		);

		$id_attr = '';
		if ( '' !== $custom_id ) {
			$id_attr = ' id="' . esc_attr( $custom_id ) . '"';
		}
		$block_content = sprintf(
			"<!-- wp:spacer %1s -->\n<div%2s style=\"height:%3s\" aria-hidden=\"true\" class=\"%4s\"></div>\n<!-- /wp:spacer -->\n",
			$attrs,
			$id_attr,
			$height,
			esc_attr( $class_attr )
		);

		// Save custom CSS to the Customizer's Additional CSS
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content;
	}

	/**
	 * Resolve spacer height from Elementor spacer settings.
	 *
	 * @param array $settings The Elementor widget settings.
	 *
	 * @return string Height value with unit.
	 */
	private function resolve_height( array $settings ): string {
		$height_data = null;

		if ( isset( $settings['height'] ) ) {
			$height_data = $settings['height'];
		} elseif ( isset( $settings['space'] ) ) {
			$height_data = $settings['space'];
		}

		$size = 20;
		$unit = 'px';

		if ( is_array( $height_data ) ) {
			if ( isset( $height_data['size'] ) && is_numeric( $height_data['size'] ) ) {
				$size = (float) $height_data['size'];
			}

			if ( isset( $height_data['unit'] ) && is_string( $height_data['unit'] ) ) {
				$candidate_unit = trim( $height_data['unit'] );
				if ( preg_match( '/^(px|em|rem|vh|vw|%)$/i', $candidate_unit ) ) {
					$unit = strtolower( $candidate_unit );
				}
			}
		} elseif ( is_numeric( $height_data ) ) {
			$size = (float) $height_data;
		}

		if ( $size < 0 ) {
			$size = 0;
		}

		$normalized_size = rtrim( rtrim( sprintf( '%.6F', $size ), '0' ), '.' );
		if ( '' === $normalized_size ) {
			$normalized_size = '0';
		}

		return $normalized_size . $unit;
	}
}
