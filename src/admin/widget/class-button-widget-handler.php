<?php
/**
 * Widget handler for Elementor button widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_url;
use function sanitize_html_class;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor button widget.
 */
class Button_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor button to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings     = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$text         = isset( $settings['text'] ) ? (string) $settings['text'] : '';
		$link         = is_array( $settings['link'] ?? null ) ? (string) ( $settings['link']['url'] ?? '' ) : '';
		$custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$custom_id    = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$text_color   = isset( $settings['button_text_color'] ) ? strtolower( (string) $settings['button_text_color'] ) : '';
		$background   = isset( $settings['background_color'] ) ? strtolower( (string) $settings['background_color'] ) : '';

		$typography = Style_Parser::parse_typography( $settings );
		$spacing    = Style_Parser::parse_spacing( $settings );
		$border     = Style_Parser::parse_border( $settings );

		$button_attributes  = array();
		$inline_style_parts = array( $typography['style'], $spacing['style'], $border['style'] );
		$class_names        = array( 'wp-block-button__link', 'wp-element-button' );

		if ( '' !== $link ) {
			$button_attributes['url'] = $link;
		}

		if ( ! empty( $typography['attributes'] ) ) {
			$button_attributes['style']['typography'] = $typography['attributes'];
		}
		if ( ! empty( $spacing['attributes'] ) ) {
			$button_attributes['style']['spacing'] = $spacing['attributes'];
		}
		if ( ! empty( $border['attributes'] ) ) {
			$button_attributes['style']['border'] = $border['attributes'];
		}

		if ( '' !== $text_color ) {
			if ( $this->is_preset_color_slug( $text_color ) ) {
				$button_attributes['textColor']                                  = $text_color;
				$button_attributes['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
			} else {
				$button_attributes['style']['color']['text']                     = $text_color;
				$button_attributes['style']['elements']['link']['color']['text'] = $text_color;
				$inline_style_parts[]                                            = 'color:' . $text_color . ';';
			}
			$class_names[] = 'has-text-color';
			$class_names[] = 'has-link-color';
		}

		if ( '' !== $background ) {
			if ( $this->is_preset_color_slug( $background ) ) {
				$button_attributes['backgroundColor'] = $background;
			} else {
				$button_attributes['style']['color']['background'] = $background;
				$inline_style_parts[]                              = 'background-color:' . $background . ';';
			}
			$class_names[] = 'has-background';
		}

		if ( '' !== $custom_class ) {
			foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
				$class = trim( $class );
				if ( '' !== $class ) {
					$class_names[] = sanitize_html_class( $class );
				}
			}
		}

		$inline_style = implode( '', array_filter( $inline_style_parts ) );

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$anchor_attributes = '' !== $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '';
		$anchor_attributes .= ! empty( $class_names ) ? ' class="' . esc_attr( implode( ' ', array_unique( $class_names ) ) ) . '"' : '';
		$anchor_attributes .= '' !== $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '';
		$anchor_attributes .= '' !== $link ? ' href="' . esc_url( $link ) . '"' : '';

		$anchor_html = sprintf( '<a%s>%s</a>', $anchor_attributes, wp_kses_post( $text ) );

		$button_block = Block_Builder::build( 'button', $button_attributes, $anchor_html );

		return Block_Builder::build( 'buttons', array(), $button_block );
	}

	/**
	 * Check if a given color value is a Gutenberg preset slug.
	 *
	 * @param string $color Color value.
	 */
	private function is_preset_color_slug( string $color ): bool {
		return '' !== $color && false === strpos( $color, '#' );
	}
}
