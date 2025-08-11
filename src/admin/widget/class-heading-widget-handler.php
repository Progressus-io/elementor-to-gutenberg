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
		$color    = $settings['title_color'] ?? '';
		$class    = ! empty( $color ) ? 'has-text-color' : '';
		$style    = ! empty( $color ) ? sprintf( 'color:%s;', esc_attr( $color ) ) : '';

		if ( isset( $settings['typography_text_transform'] ) ) {
			$class .= ' has-text-transform-' . esc_attr( $settings['typography_text_transform'] );
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
			'<!-- wp:heading %s --><h2 class="wp-block-heading %s" style="%s">%s</h2><!-- /wp:heading -->' . "\n",
			$attrs,
			$class,
			$style,
			esc_html( $title )
		);

		return $block_content;
	}
}