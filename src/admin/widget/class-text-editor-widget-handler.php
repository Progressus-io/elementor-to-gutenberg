<?php
/**
 * Widget handler for Elementor text-editor widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor text-editor widget.
 */
class Text_Editor_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor text-editor to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = $element['settings'] ?? array();
		$text     = $settings['editor'] ?? '';
		$color    = $settings['text_color'] ?? '';
		$class    = ! empty( $color ) ? 'has-text-color' : '';
		$style    = ! empty( $color ) ? sprintf( 'color:%s;', esc_attr( $color ) ) : '';
		$custom_class = $settings['_css_classes'] ?? '';
		$custom_id    = $settings['_element_id'] ?? '';
		$custom_css   = $settings['custom_css'] ?? '';

		if ( isset( $settings['typography_text_transform'] ) ) {
			$class .= ' has-text-transform-' . esc_attr( $settings['typography_text_transform'] );
		}

		if ( ! empty( $custom_class ) ) {
			$class .= ' ' . esc_attr( $custom_class );
		}
		$typography   = Style_Parser::parse_typography( $settings );
		$style       .= $typography['style'];
		$attrs_array  = array(
			'style' => array(
				'color'      => array( 'text' => $color ),
				'typography' => $typography['attributes'],
			),
		);
		$attrs_array  = array_merge_recursive( $attrs_array, Style_Parser::parse_spacing( $settings ) );
		$attrs        = wp_json_encode( $attrs_array );

		$block_content  = sprintf(
			'<!-- wp:html %s --><div class="wp-block-paragraph %s" id="%s" style="%s">%s</div><!-- /wp:html -->' . "\n",
			$attrs,
			$class,
			$custom_id,
			$style,
			wp_kses_post( $text )
		);

		// Save custom CSS to the Customizer's Additional CSS
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content;
	}
}