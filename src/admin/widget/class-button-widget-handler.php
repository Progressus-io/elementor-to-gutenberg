<?php
/**
 * Widget handler for Elementor button widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor button widget.
 */
class Button_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor button to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings     = $element['settings'] ?? array();
		$text         = $settings['text'] ?? '';
		$url          = $settings['link']['url'] ?? '';
		$custom_class = $settings['_css_classes'] ?? '';
		$custom_id    = $settings['_element_id'] ?? '';
		$custom_css   = $settings['custom_css'] ?? '';

		$class = '';
		if ( ! empty( $custom_class ) ) {
			$class .= ' ' . esc_attr( $custom_class );
		}

		$attrs_array = array();
		$inline_style = '';

		if ( $url ) {
			$attrs_array['url'] = esc_url( $url );
		}

		if ( ! empty( $settings['button_text_color'] ) ) {
			$txt = strtolower( $settings['button_text_color'] );
			$class .= ' has-text-color has-link-color';
			if ( $this->is_preset_color_slug( $txt ) ) {
				$attrs_array['textColor'] = $txt;
				$class .= ' has-text-color has-link-color';
				$attrs_array['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $txt;
			} else {
				$attrs_array['style']['color']['text'] = $txt;
				$attrs_array['style']['elements']['link']['color']['text'] = $txt;
				$inline_style .= 'color:' . $txt . ';';
			}

		}

		if ( ! empty( $settings['background_color'] ) ) {
			$bg = strtolower( $settings['background_color'] );
			$class .= ' has-background';
			if ( $this->is_preset_color_slug( $bg ) ) {
				$attrs_array['backgroundColor'] = $bg;
			} else {
				$attrs_array['style']['color']['background'] = $bg;
				$inline_style .= 'background-color:' . $bg . ';';
			}
		}

		// Typography, spacing, border.
		$typography = Style_Parser::parse_typography( $settings );
		$spacing    = Style_Parser::parse_spacing( $settings );
		$border     = Style_Parser::parse_border( $settings );

		if ( ! empty( $typography['attributes'] ) ) {
			$attrs_array['style']['typography'] = $typography['attributes'];
		}
		if ( ! empty( $spacing['attributes'] ) ) {
			$attrs_array['style']['spacing'] = $spacing['attributes'];
		}
		if ( ! empty( $border['attributes'] ) ) {
			$attrs_array['style']['border'] = $border['attributes'];
		}

		// Inline style fallback (optional, safe for editor).
		$inline_style .= $typography['style'] . $spacing['style'] . $border['style'];

		// Encode attributes.
		$attrs = wp_json_encode( $attrs_array );

		// Build block content.
		$block_content = sprintf(
			'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button %1s --><div class="wp-block-button"><a id="%2s" class="wp-block-button__link %3s wp-element-button"%4s%5s>%6s</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' . "\n",
			$attrs,
			esc_attr( $custom_id ),
			esc_attr( $class ),
			$inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
			$url ? ' href="' . esc_url( $url ) . '"' : '',
			esc_html( $text )
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
