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
use function wp_strip_all_tags;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor button widget.
 */
class Button_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor button to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 */
	public function handle( array $element ): string {
		$settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$text       = isset( $settings['text'] ) ? trim( (string) $settings['text'] ) : '';
		$link_data  = is_array( $settings['link'] ?? null ) ? $settings['link'] : array();
		$url        = isset( $link_data['url'] ) ? esc_url( (string) $link_data['url'] ) : '';
		$custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_raw = isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '';
		$text_color = isset( $settings['button_text_color'] ) ? strtolower( (string) $settings['button_text_color'] ) : '';
		$background = isset( $settings['background_color'] ) ? strtolower( (string) $settings['background_color'] ) : '';

		if ( '' === $text ) {
			$text = isset( $link_data['custom_text'] ) ? trim( (string) $link_data['custom_text'] ) : '';
		}

		if ( '' === $text && '' === $url ) {
			return '';
		}

		$custom_classes = array();
		if ( '' !== $custom_raw ) {
			foreach ( preg_split( '/\s+/', $custom_raw ) as $class ) {
				$clean = Style_Parser::clean_class( $class );
				if ( '' === $clean ) {
					continue;
				}
				$custom_classes[] = $clean;
			}
		}

		$button_attributes = array();
		if ( ! empty( $custom_classes ) ) {
			$button_attributes['className'] = implode( ' ', array_unique( $custom_classes ) );
		}

		$anchor_classes = array( 'wp-block-button__link', 'wp-element-button' );
		$anchor_style   = array();

		if ( '' !== $text_color ) {
			if ( $this->is_preset_color_slug( $text_color ) ) {
				$button_attributes['textColor'] = $text_color;
				$anchor_classes[]               = 'has-text-color';
				$anchor_classes[]               = 'has-' . Style_Parser::clean_class( $text_color ) . '-color';
			} elseif ( $this->is_hex_color( $text_color ) ) {
				$button_attributes['style']['color']['text'] = $text_color;
				$anchor_classes[]                            = 'has-text-color';
				$anchor_style[]                              = 'color:' . $text_color;
			}
		}

		if ( '' !== $background ) {
			if ( $this->is_preset_color_slug( $background ) ) {
				$button_attributes['backgroundColor'] = $background;
				$anchor_classes[]                     = 'has-background';
				$anchor_classes[]                     = 'has-' . Style_Parser::clean_class( $background ) . '-background-color';
			} elseif ( $this->is_hex_color( $background ) ) {
				$button_attributes['style']['color']['background'] = $background;
				$anchor_classes[]                                  = 'has-background';
				$anchor_style[]                                    = 'background-color:' . $background;
			}
		}

		if ( '' !== $url ) {
			$button_attributes['url'] = $url;
		}

		$rel_tokens = array();
		if ( ! empty( $link_data['is_external'] ) ) {
			$button_attributes['linkTarget'] = '_blank';
			$rel_tokens[]                    = 'noopener';
		}

		if ( ! empty( $link_data['nofollow'] ) ) {
			$rel_tokens[] = 'nofollow';
		}

		if ( ! empty( $rel_tokens ) ) {
			$button_attributes['rel'] = implode( ' ', array_unique( $rel_tokens ) );
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$anchor_attrs   = array();
		$anchor_attrs[] = 'class="' . esc_attr( implode( ' ', array_unique( $anchor_classes ) ) ) . '"';

		if ( '' !== $url ) {
			$anchor_attrs[] = 'href="' . $url . '"';
		}

		if ( ! empty( $link_data['is_external'] ) ) {
			$anchor_attrs[] = 'target="_blank"';
		}

		if ( ! empty( $rel_tokens ) ) {
			$anchor_attrs[] = 'rel="' . esc_attr( implode( ' ', array_unique( $rel_tokens ) ) ) . '"';
		}

		if ( ! empty( $anchor_style ) ) {
			$anchor_attrs[] = 'style="' . esc_attr( implode( ';', $anchor_style ) ) . '"';
		}

		$anchor_html = sprintf(
			'<a %s>%s</a>',
			implode( ' ', $anchor_attrs ),
			wp_strip_all_tags( $text )
		);

		$button_block = Block_Builder::build( 'button', $button_attributes, $anchor_html );

		return Block_Builder::build( 'buttons', array(), $button_block );
	}

	/**
	 * Check if a given color value is a Gutenberg preset slug.
	 */
	private function is_preset_color_slug( string $color ): bool {
		return '' !== $color && false === strpos( $color, '#' );
	}

	/**
	 * Determine if the provided color string is hexadecimal.
	 */
	private function is_hex_color( string $color ): bool {
		return 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color );
	}
}
