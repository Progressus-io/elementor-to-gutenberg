<?php
/**
 * Widget handler for Elementor heading widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor heading widget.
 */
class Heading_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor heading to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = $element['settings'] ?? array();
		$title    = $settings['title'] ?? '';
		$level    = str_split( $settings['header_size'] )[1] ?? 2;
		$color    = $settings['title_color'] ?? '';
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
			'level' => ( int ) $level,
			'style' => array(
				'color'      => array( 'text' => $color ),
				'typography' => $typography['attributes'],
			),
		);
		$attrs_array  = array_merge_recursive( $attrs_array, Style_Parser::parse_spacing( $settings ) );
		$attrs        = wp_json_encode( $attrs_array );

		$block_content  = sprintf(
			'<!-- wp:heading %s --><h%s class="wp-block-heading %s" id="%s" style="%s">%s</h%s><!-- /wp:heading -->' . "\n",
			$attrs,
			esc_html( $level ),
			$class,
			esc_attr( $custom_id ),
			$style,
			esc_html( $title ),
			esc_html( $level )
		);
		// Save custom CSS to the Customizer's Additional CSS
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}
		return $block_content;
	}
}