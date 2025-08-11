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
			'<!-- wp:html %s --><div class="wp-block-paragraph %s" style="%s">%s</div><!-- /wp:html -->' . "\n",
			$attrs,
			$class,
			$style,
			wp_kses_post( $text )
		);

		return $block_content;
	}
}