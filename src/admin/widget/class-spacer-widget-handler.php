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
		$settings     = $element['settings'] ?? array();

		// Spacer height from Elementor's "space".
		$space   = $settings['space']['size'] ?? 20;
		$unit    = $settings['space']['unit'] ?? 'px';
		$height  = $space . $unit;

		// Custom ID, classes, CSS.
		$custom_id    = $settings['_element_id'] ?? '';
		$custom_class = $settings['_css_classes'] ?? '';
		$custom_css   = $settings['custom_css'] ?? '';

		// Spacing (margin + padding).
		$spacing = Style_Parser::parse_spacing( $settings );

		// Merge all inline styles.
		$inline_style = "height:{$height};" . $spacing['style'];

		// Build attributes for wp:spacer block.
		$attrs_array = array(
			'height' => $height,
			'className' => trim( $custom_class ),
		);

		if ( ! empty( $spacing['attributes'] ) ) {
			$attrs_array['style']['spacing'] = $spacing['attributes'];
		}

		$attrs = wp_json_encode( $attrs_array );

		// Final block output.
		$block_content = sprintf(
			'<!-- wp:spacer %s --><div %s class="wp-block-spacer %s"%s aria-hidden="true"></div><!-- /wp:spacer -->' . "\n",
			$attrs,
			! empty( $custom_id ) ? 'id="' . esc_attr( $custom_id ) . '"' : '',
			esc_attr( $custom_class ),
			$inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : ''
		);

		// Save custom CSS if provided.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content;
	}
}
