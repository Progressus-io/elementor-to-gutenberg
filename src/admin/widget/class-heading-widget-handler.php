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
		$settings      = $element['settings'] ?? array();
		$title         = $settings['title'] ?? '';
		$level         = str_split( $settings['header_size'] )[1] ?? 2;
		$text_color    = ! empty( $settings['title_color'] ) ? strtolower( $settings['title_color'] ) : '';
		$custom_class  = $settings['_css_classes'] ?? '';
		$unique_class  = 'heading-' . uniqid();
		$custom_id     = $settings['_element_id'] ?? '';
		$custom_css    = $settings['custom_css'] ?? '';
		$custom_class .= ' ' . $unique_class;

		$class = 'wp-block-heading';

		// Handle text transform.
		if ( ! empty( $settings['typography_text_transform'] ) ) {
			$class .= 'has-text-transform-' . esc_attr( $settings['typography_text_transform'] );
		}
		if ( ! empty( $custom_class ) ) {
			$class .= ' ' . esc_attr( $custom_class );
		}

		$typography  = Style_Parser::parse_typography( $settings );
		$border      = Style_Parser::parse_border( $settings );
		$spacing	 = Style_Parser::parse_spacing( $settings );

		$attrs_array['level'] = (int) $level;
		// Handle text + link color.
		$inline_style = '';
		if ( $this->is_preset_color_slug( $text_color ) ) {
			// Preset slug.
			$attrs_array['textColor'] = $text_color;
			$attrs_array['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
			$class .= ' has-text-color has-link-color';
		} elseif ( ! empty( $text_color ) ) {
			// Raw hex.
			$attrs_array['style']['color']['text'] = $text_color;
			$attrs_array['style']['elements']['link']['color']['text'] = $text_color;
			$class .= ' has-text-color has-link-color';

			// Add inline style for both text + link.
			$inline_style .= 'color:' . esc_attr( $text_color ) . ';';
		}

		$attrs_array['className'] = trim( $class );

		if ( ! empty( $typography['attributes'] ) ) {
			$attrs_array['style']['typography'] = $typography['attributes'];
		}
		if ( ! empty( $spacing['attributes'] ) ) {
			$attrs_array['style']['spacing'] = $spacing['attributes'];
		}
		if ( ! empty( $border['attributes'] ) ) {
			$attrs_array['style']['border'] = $border['attributes'];
		}
		$inline_style .= $typography['style'] . $border['style'] . $spacing['style'];
		// Encode block attributes.
		$attrs = wp_json_encode( $attrs_array );

		// Build block output.
		$block_content = sprintf(
			'<!-- wp:heading %s --><h%s id="%s" class="%s"%s>%s</h%s><!-- /wp:heading -->' . "\n",
			$attrs,
			esc_html( $level ),
			esc_attr( $custom_id ),
			esc_attr( $class ),
			$inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
			esc_html( $title ),
			esc_html( $level )
		);

		// Save custom CSS if any.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content;
	}

	/**
	 * Check if a given color value is a Gutenberg preset slug.
	 *
	 * @param string $color Color value.
	 * @return bool
	 */
	private function is_preset_color_slug( string $color ): bool {
		return ! empty( $color ) && strpos( $color, '#' ) === false;
	}
}
