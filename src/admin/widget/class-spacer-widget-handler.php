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

		$height = 20;
		if ( isset( $settings['height'] ) ) {
			if ( is_array( $settings['height'] ) && isset( $settings['height']['size'] ) && is_numeric( $settings['height']['size'] ) ) {
				$height = (int) round( (float) $settings['height']['size'] );
			} elseif ( is_numeric( $settings['height'] ) ) {
				$height = (int) round( (float) $settings['height'] );
			}
		}

		if ( $height < 0 ) {
			$height = 0;
		}

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
				'height' => $height . 'px',
			)
		);

		$id_attr = '';
		if ( '' !== $custom_id ) {
			$id_attr = ' id="' . esc_attr( $custom_id ) . '"';
		}
		$block_content = sprintf(
			"<!-- wp:spacer %1s -->\n<div%2s style=\"height:%3spx\" aria-hidden=\"true\" class=\"%4s\"></div>\n<!-- /wp:spacer -->\n",
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
}