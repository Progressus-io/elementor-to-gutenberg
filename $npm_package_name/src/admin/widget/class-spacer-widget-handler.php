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
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$height        = isset( $settings['height'] ) ? intval( $settings['height'] ) : 20;
		$attrs         = wp_json_encode( array( 'height' => $height . 'px' ) );
		$custom_class = $settings['_css_classes'] ?? '';
		$custom_id    = $settings['_element_id'] ?? '';
		$custom_css   = $settings['custom_css'] ?? '';

		$block_content = sprintf(
			"<!-- wp:spacer %1s -->\n<div %2s style=\"height: %3spx\" aria-hidden=\"true\" class=\"wp-block-spacer %4s\"></div>\n<!-- /wp:spacer -->\n",
			$attrs,
			! empty( $custom_id ) ? 'id="' . esc_attr( $custom_id ) . '"' : '',
			$height,
			esc_attr( $custom_class )
		);

		// Save custom CSS to the Customizer's Additional CSS
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}
		return $block_content;
	}
}